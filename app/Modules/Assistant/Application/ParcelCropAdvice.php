<?php

namespace App\Modules\Assistant\Application;

use App\Exceptions\AssistantUnavailable;
use App\Models\User;
use App\Modules\Assistant\Infrastructure\CropAdviceAgent;
use App\Platform\Observability\UsageMetrics;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Explicit, budgeted crop advice. The provider receives only allowlisted category
 * labels and reviewed agriculture passages — never names, IDs, places, coordinates
 * or free text. Callers derive the categories from authorized saved evidence.
 */
final class ParcelCropAdvice
{
    /** The complete provider allowlist: field => value => application-owned label. */
    public const CATEGORIES = [
        'crop' => ['rice' => 'Rice / Palay', 'corn' => 'Corn', 'vegetables' => 'Vegetables', 'root_crops' => 'Root crops',
            'fruit' => 'Fruit', 'legumes' => 'Legumes', 'mixed' => 'Mixed crops', 'other' => 'Other crop'],
        'season' => ['dry' => 'Dry season', 'wet' => 'Wet season'],
        'growth_stage' => ['not_planted' => 'Not planted yet', 'establishment' => 'Newly planted', 'vegetative' => 'Leaf growth',
            'flowering' => 'Flowering', 'grain_filling' => 'Grain or pod filling', 'mature' => 'Mature', 'harvested' => 'Harvested',
            'unknown' => 'Unknown (no current field check)'],
        'condition' => ['normal' => 'No visible problem observed', 'wilting' => 'Wilting', 'yellowing' => 'Yellowing leaves',
            'waterlogged' => 'Waterlogged', 'unknown' => 'Unknown (not checked recently)'],
        'water_supply' => ['reliable' => 'Reliable', 'limited' => 'Limited', 'unavailable' => 'No water available',
            'unknown' => 'Unknown (not verified recently)'],
        'satellite_trend' => ['falling' => 'Falling', 'stable' => 'About the same', 'rising' => 'Rising',
            'unavailable' => 'Unavailable (no comparable saved pictures)'],
        'rainfall' => ['below_reference' => 'Recent rain below the multi-year reference',
            'little_rain_forecast' => 'Little rain forecast this week', 'no_dry_signal' => 'No dry-weather trigger in current readings',
            'unavailable' => 'Unavailable'],
    ];

    private const QUESTION_LABELS = ['crop' => 'Crop', 'season' => 'Season', 'growth_stage' => 'Growth stage',
        'condition' => 'Observed field condition', 'water_supply' => 'Water supply',
        'satellite_trend' => 'Satellite greenness trend', 'rainfall' => 'Municipality-level rainfall (area, not this field)'];

    private const MAXIMUM_EVIDENCE_CHARACTERS = 7000;

    private const CACHE_SECONDS = 86400;

    public function __construct(
        private OfficeAssistant $assistant,
        private AssistantKnowledge $knowledge,
        private AssistantGroundedAnswer $grounding,
        private AssistantRequestBudget $budget,
    ) {}

    public function available(User $user): bool
    {
        return Gate::forUser($user)->allows('use', OfficeAssistant::class) && ! $user->isProvincialVeterinaryOffice()
            && $this->assistant->configured();
    }

    /** The exact application-owned provider question. Rejects anything outside the allowlist. */
    public function question(array $categories): string
    {
        $categories = $this->validated($categories);
        $lines = [];
        foreach (self::QUESTION_LABELS as $field => $label) {
            $lines[] = $label.': '.self::CATEGORIES[$field][$categories[$field]];
        }

        return "What general crop-care checks should agriculture-office staff consider for a field with these conditions?\n".implode("\n", $lines);
    }

    /** @return list<string> Reviewed agriculture topics, primary first. */
    public function topics(array $categories): array
    {
        $c = $this->validated($categories);
        $active = in_array($c['growth_stage'], ['establishment', 'vegetative', 'flowering', 'grain_filling', 'unknown'], true);
        $dry = in_array($c['rainfall'], ['below_reference', 'little_rain_forecast'], true);

        return match (true) {
            $c['condition'] === 'yellowing' => ['pest-management', 'crop-nutrition'],
            $c['condition'] === 'wilting' => ['irrigation', 'pest-management'],
            $c['condition'] === 'waterlogged' => ['irrigation', 'soil-health'],
            $active && in_array($c['water_supply'], ['limited', 'unavailable'], true) => ['irrigation', 'soil-health'],
            in_array($c['growth_stage'], ['not_planted', 'establishment'], true) => ['crop-establishment', 'seed-selection'],
            in_array($c['growth_stage'], ['mature', 'harvested'], true) => ['crop-rotation', 'soil-health'],
            $active && ($dry || in_array($c['growth_stage'], ['flowering', 'grain_filling'], true)) => ['irrigation', 'pest-management'],
            default => ['pest-management', 'crop-nutrition'],
        };
    }

    public function advise(User $user, array $categories): array
    {
        Gate::forUser($user)->authorize('use', OfficeAssistant::class);
        abort_if($user->isProvincialVeterinaryOffice(), 403, 'Crop advice is available to agriculture office accounts only.');
        $question = $this->question($categories);
        if (! $this->assistant->configured()) {
            throw new AssistantUnavailable('AI crop advice is not available. Ask your administrator to enable the Assistant.');
        }
        $evidence = $this->evidence($user, $this->topics($categories));
        $agent = new CropAdviceAgent($evidence['passages']);
        $normalized = $this->validated($categories);
        ksort($normalized);
        // Key identity holds only the allowlisted categories plus prompt/corpus/model identity.
        $key = 'assistant:crop-advice:v1:'.hash('sha256', json_encode([
            'schema' => array_map(static fn (Type $type): array => $type->toArray(), $agent->schema(new JsonSchemaTypeFactory)),
            'generation' => ['strict' => true, 'temperature' => 0, 'max_tokens' => 600],
            'agent_revision' => hash_file('sha256', app_path('Modules/Assistant/Infrastructure/CropAdviceAgent.php')),
            'categories' => $normalized, 'knowledge' => $evidence['version'],
            'passages' => array_column($evidence['passages'], 'id'), 'model' => config('assistant.model'),
            'question' => $question, 'instructions' => $agent->instructions(),
        ], JSON_THROW_ON_ERROR));
        $lock = null;
        $acquired = false;
        try {
            $cached = $this->grounding->validate(Cache::get($key), $evidence['passages']);
            app(UsageMetrics::class)->cache('assistant-help', $cached !== null);
            if ($cached) {
                return $this->result($cached, $evidence, 'cache');
            }
            $lock = Cache::lock($key.':lock', max(30, min(90, (int) config('assistant.timeout_seconds') + 10)));
            $acquired = $lock->get();
            if (! $acquired) {
                return $this->fallback($evidence);
            }
            if ($cached = $this->grounding->validate(Cache::get($key), $evidence['passages'])) {
                return $this->result($cached, $evidence, 'cache');
            }
            // Shared atomic Assistant allowance; exhaustion or uncertainty stops before inference.
            $this->budget->reserve($user);
            $started = hrtime(true);
            $received = false;
            app(UsageMetrics::class)->providerAttempt('openai');
            try {
                $response = $agent->prompt($question, provider: 'openai', model: (string) config('assistant.model'),
                    timeout: (int) config('assistant.timeout_seconds'));
                $received = true;
                app(UsageMetrics::class)->providerFinished('openai', (hrtime(true) - $started) / 1000000, false);
                app(UsageMetrics::class)->tokens($response->usage->inputTokens, $response->usage->outputTokens);
                $output = $response instanceof StructuredAgentResponse ? $response->toArray() : null;
                $validated = $this->grounding->validate($output, $evidence['passages']);
                if ($validated === null) {
                    return $this->fallback($evidence, $response->usage->inputTokens, $response->usage->outputTokens);
                }
            } catch (Throwable $exception) {
                if (! $received) {
                    app(UsageMetrics::class)->providerFinished('openai', (hrtime(true) - $started) / 1000000, true);
                }
                Log::warning('Crop advice could not be generated.', ['exception_type' => $exception::class]);

                return $this->fallback($evidence);
            }
            try {
                if (! Cache::put($key, $output, self::CACHE_SECONDS)) {
                    Log::warning('Crop advice could not be cached.');
                }
            } catch (Throwable $exception) {
                Log::warning('Crop advice could not be cached.', ['exception_type' => $exception::class]);
            }

            return $this->result($validated, $evidence, 'ai', $response->usage->inputTokens, $response->usage->outputTokens);
        } catch (AssistantUnavailable $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Crop advice cache could not be verified.', ['exception_type' => $exception::class]);
            throw new AssistantUnavailable('AI crop advice is temporarily unavailable. Your office records are unaffected. Try again shortly.');
        } finally {
            if ($lock && $acquired) {
                try {
                    $lock->release();
                } catch (Throwable $exception) {
                    Log::warning('Crop advice lock could not be released.', ['exception_type' => $exception::class]);
                }
            }
        }
    }

    private function validated(array $categories): array
    {
        if (array_diff_key(self::CATEGORIES, $categories) !== [] || array_diff_key($categories, self::CATEGORIES) !== []) {
            throw new AssistantUnavailable('Crop advice needs a supported set of parcel conditions.', 422);
        }
        $clean = [];
        foreach (self::CATEGORIES as $field => $values) {
            $value = $categories[$field];
            if ($field === 'crop' && $value === 'not_recorded') {
                throw new AssistantUnavailable('Record this season’s crop, or a current field check, before asking for crop advice.', 422);
            }
            if (! is_string($value) || ! isset($values[$value])) {
                throw new AssistantUnavailable('Crop advice needs a supported set of parcel conditions.', 422);
            }
            $clean[$field] = $value;
        }

        return $clean;
    }

    private function evidence(User $user, array $topics): array
    {
        $passages = [];
        $characters = 0;
        $version = '';
        try {
            foreach ($topics as $topic) {
                $retrieved = $this->knowledge->retrieve($user, $topic, OfficeAssistant::TOPICS[$topic]['question']);
                $version = $retrieved['version'];
                foreach ($retrieved['passages'] as $passage) {
                    $size = mb_strlen($passage['text']);
                    if (isset($passages[$passage['id']]) || count($passages) === 3 || $characters + $size > self::MAXIMUM_EVIDENCE_CHARACTERS) {
                        continue;
                    }
                    $passages[$passage['id']] = $passage;
                    $characters += $size;
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Crop advice guidance could not be retrieved.', ['exception_type' => $exception::class]);
            $passages = [];
        }
        if ($passages === []) {
            throw new AssistantUnavailable('Reviewed crop guidance is unavailable. Ask your administrator to check the Assistant knowledge files.');
        }

        return ['version' => $version, 'passages' => array_values($passages), 'characters' => $characters];
    }

    private function result(array $validated, array $evidence, string $delivery, int $input = 0, int $output = 0): array
    {
        return $validated + ['delivery' => $delivery, 'input_tokens' => max(0, $input), 'output_tokens' => max(0, $output),
            'retrieved_passages' => count($evidence['passages'])];
    }

    /** Provider failure, rejected output or contention: show the saved primary passage instead. */
    private function fallback(array $evidence, int $input = 0, int $output = 0): array
    {
        $passage = $evidence['passages'][0];

        return $this->result(['answer' => "An AI answer is not available right now. Here is the reviewed guidance.\n\n".$passage['text'],
            'sources' => [['id' => $passage['id'], 'title' => $passage['title']]]], $evidence, 'guide', $input, $output);
    }
}
