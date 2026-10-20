<?php

namespace App\Modules\Assistant\Application;

use App\Exceptions\AssistantUnavailable;
use App\Models\User;
use App\Modules\Assistant\Infrastructure\OfficeHelpAgent;
use App\Platform\Observability\UsageMetrics;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

final class OfficeAssistant
{
    public const MODULES = ['dashboard', 'farmers', 'crops', 'satellite', 'climate-risk', 'assistance', 'coverage', 'harvests', 'animal-health', 'cooperatives', 'machinery', 'accounts', 'geofences', 'backups', 'audit', 'other'];

    /** Provider questions are application-owned; the visible chat never becomes a prompt. */
    public const TOPICS = [
        ...AssistantAgriculture::TOPICS,
        'crop-drawing' => ['module' => 'crops', 'question' => 'How do authorized office staff draw and save a planted crop section inside an existing parcel?'],
        'parcel-drawing' => ['module' => 'farmers', 'question' => 'How do authorized office staff select a farmer and draw or import a whole farm parcel boundary?'],
        'seed-import' => ['module' => 'assistance', 'question' => 'How do authorized office staff import rice seed releases and correct a workbook validation error?'],
        'ndvi' => ['module' => 'crops', 'question' => 'What does the parcel Satellite / NDVI feature show, and what are its coverage and interpretation limits?'],
        'ndmi' => ['module' => 'satellite', 'question' => 'How do authorized office users use NDMI in the satellite field check, and what can vegetation water-content readings tell them and not tell them?'],
        'savi' => ['module' => 'satellite', 'question' => 'How do authorized office users use SAVI for sparse or newly planted vegetation, and what are its soil-background and coverage limits?'],
        'evi' => ['module' => 'satellite', 'question' => 'How do authorized office users use EVI for dense vegetation, and what are its interpretation and coverage limits?'],
        'satellite-indices' => ['module' => 'satellite', 'question' => 'How do authorized office users choose between NDVI, NDMI, SAVI and EVI in the satellite field check, and what can these indices measure and not measure?'],
        'climate-readiness' => ['module' => 'climate-risk', 'question' => 'How do authorized office users read the El Niño readiness dashboard, understand missing assessments and automated evidence, and record a verified office assessment when their role permits?'],
        'animals' => ['module' => 'animal-health', 'question' => 'How do authorized office staff record multiple animals or groups and their Animal Health services for one owner?'],
        'access' => ['module' => 'accounts', 'question' => 'What can this office role access, and how are municipality, province and regional permissions enforced?'],
        'farmer-login' => ['module' => 'farmers', 'question' => 'How do authorized office staff issue or recover farmer portal access, and how do farmers sign in?'],
        'farmer-id' => ['module' => 'farmers', 'question' => 'How do authorized office staff open, print and export a farmer registry card, including its signature lines?'],
        'harvest-entry' => ['module' => 'harvests', 'question' => 'How do authorized office staff enter a harvest record, and how does a seed release differ from a planted crop section?'],
        'help' => ['module' => 'other', 'question' => 'What documented tasks can the eAgriLGU Assistant explain for this office role?'],
        'dashboard-overview' => ['module' => 'dashboard', 'question' => 'How do authorized office users read the dashboard and its current and selected-year agriculture summaries?'],
        'weather' => ['module' => 'farmers', 'question' => 'How do authorized office users read municipality weather guidance and find official PAGASA bulletins?'],
        'cooperatives' => ['module' => 'cooperatives', 'question' => 'How do authorized office staff manage cooperative records and membership?'],
        'machinery' => ['module' => 'machinery', 'question' => 'How do authorized office staff track machinery inventory, status and maintenance?'],
        'backups' => ['module' => 'backups', 'question' => 'How do authorized office staff use the Backup Folder and private file previews or downloads?'],
        'audit' => ['module' => 'audit', 'question' => 'How do authorized oversight users filter and export the audit trail, and what are its scope limits?'],
        'geofences' => ['module' => 'geofences', 'question' => 'How do authorized users view or manage municipality geofence planning references and their map appearance?'],
        'system-overview' => ['module' => 'other', 'question' => 'What is eAgriLGU and what implemented office and farmer workflows does it support?'],
        'ai-technology' => ['module' => 'other', 'question' => 'What AI technology powers the eAgriLGU Assistant, and how do its reviewed retrieval and local record answers work?'],
        'assistant-privacy' => ['module' => 'other', 'question' => 'Who is the eAgriLGU Assistant, what can it answer, and what information stays private or is sent to OpenAI?'],
        'data-quality' => ['module' => 'farmers', 'question' => 'How do authorized staff review incomplete farmer references, duplicate claims and import validation errors without inventing missing information?'],
        'farmer-registry' => ['module' => 'farmers', 'question' => 'How do authorized office staff create or update farmer registry records and import approved farmer workbooks?'],
        'assistance-management' => ['module' => 'assistance', 'question' => 'How do authorized office staff record agriculture and fisheries releases, quantities and rice distribution sheets?'],
        'assistance-coverage' => ['module' => 'assistance', 'question' => 'How do authorized office users read the assistance coverage report and map with its program, period and unit limits?'],
    ];

    public const VETERINARY_TOPICS = ['animals', 'access', 'help', 'ai-technology', 'assistant-privacy', 'livestock-care'];

    public const STYLES = ['plain', 'brief', 'steps', 'example', 'more'];

    public function __construct(
        private AssistantRequestBudget $budget,
        private AssistantKnowledge $knowledge,
        private AssistantGroundedAnswer $grounding,
    ) {}

    public function configured(): bool
    {
        return config('assistant.enabled') && trim((string) config('ai.providers.openai.key')) !== ''
            && config('ai.providers.openai.store') === false
            && config('ai.providers.openai.url') === 'https://api.openai.com/v1';
    }

    public function answerTopic(User $user, string $topic, string $language = 'en', string $style = 'plain'): array
    {
        Gate::forUser($user)->authorize('use', self::class);
        abort_unless(isset(self::TOPICS[$topic]) && in_array($language, ['en', 'fil'], true) && in_array($style, self::STYLES, true), 422, 'Choose a supported help question.');
        if ($user->isProvincialVeterinaryOffice()) {
            abort_unless(in_array($topic, self::VETERINARY_TOPICS, true), 403, 'Your veterinary account can use Animal Health and Assistant information only.');
        }
        if (! $this->configured()) {
            throw new AssistantUnavailable('The Assistant is not available yet. Ask your administrator to enable it.');
        }
        $approved = self::TOPICS[$topic];
        if ($user->isProvincialVeterinaryOffice() && in_array($topic, ['access', 'help'], true)) {
            $approved = ['module' => 'animal-health', 'question' => AssistantKnowledge::VETERINARY_QUESTION];
        }
        try {
            $evidence = $this->knowledge->retrieve($user, $topic, $approved['question']);
        } catch (Throwable $exception) {
            Log::warning('Assistant guidance could not be retrieved.', ['exception_type' => $exception::class]);

            return $this->fallback(['version' => '', 'passages' => [], 'characters' => 0], $language);
        }
        if (! $evidence['passages']) {
            return $this->fallback($evidence, $language);
        }
        $agent = new OfficeHelpAgent($user->role, $approved['module'], $evidence['passages'], $language, $style);
        // No account identity or operational content enters this shared cache.
        // Exact role and current filtered evidence prevent cross-role reuse.
        $key = 'assistant:help:v1:'.hash('sha256', json_encode([
            'schema' => array_map(static fn (Type $type): array => $type->toArray(), $agent->schema(new JsonSchemaTypeFactory)),
            'generation' => ['strict' => true, 'temperature' => 0, 'max_tokens' => 600],
            'agent_revision' => hash_file('sha256', app_path('Modules/Assistant/Infrastructure/OfficeHelpAgent.php')),
            'role' => $user->role, 'topic' => $topic, 'language' => $language,
            'model' => config('assistant.model'), 'knowledge' => $evidence['version'],
            'instructions' => $agent->instructions(), 'question' => $approved['question'],
        ], JSON_THROW_ON_ERROR));
        $lock = null;
        $acquired = false;
        try {
            $cached = $this->cached($key, $evidence);
            app(UsageMetrics::class)->cache('assistant-help', $cached !== null);
            if ($cached) {
                return $this->result($cached, $evidence, 'cache');
            }
            $lock = Cache::lock($key.':lock', max(30, min(90, (int) config('assistant.timeout_seconds') + 10)));
            $acquired = $lock->get();
            if (! $acquired) {
                return $this->fallback($evidence, $language);
            }
            if ($cached = $this->cached($key, $evidence)) {
                return $this->result($cached, $evidence, 'cache');
            }
            // Reservation failures deliberately propagate; inference cannot run
            // when the atomic allowance is exhausted or cannot be verified.
            $this->budget->reserve($user);
            $started = hrtime(true);
            $received = false;
            app(UsageMetrics::class)->providerAttempt('openai');
            try {
                $response = $agent->prompt($approved['question'], provider: 'openai',
                    model: (string) config('assistant.model'), timeout: (int) config('assistant.timeout_seconds'));
                $received = true;
                app(UsageMetrics::class)->providerFinished('openai', (hrtime(true) - $started) / 1000000, false);
                app(UsageMetrics::class)->tokens($response->usage->inputTokens, $response->usage->outputTokens);
                $output = $response instanceof StructuredAgentResponse ? $response->toArray() : null;
                $validated = $this->grounding->validate($output, $evidence['passages']);
                if ($validated === null) {
                    return $this->fallback($evidence, $language, $response->usage->inputTokens, $response->usage->outputTokens);
                }
            } catch (Throwable $exception) {
                if (! $received) {
                    app(UsageMetrics::class)->providerFinished('openai', (hrtime(true) - $started) / 1000000, true);
                }
                // Provider messages, bodies and traces may carry credentials.
                Log::warning('Assistant response could not be generated.', ['exception_type' => $exception::class]);

                return $this->fallback($evidence, $language);
            }
            // Only accepted, general guidance is cached. Rejected output and
            // record reports never become reusable AI context.
            try {
                if (! Cache::put($key, $output, 600)) {
                    Log::warning('Assistant guidance could not be cached.');
                }
            } catch (Throwable $exception) {
                Log::warning('Assistant guidance could not be cached.', ['exception_type' => $exception::class]);
            }

            return $this->result($validated, $evidence, 'ai', $response->usage->inputTokens, $response->usage->outputTokens);
        } catch (AssistantUnavailable $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Assistant guidance cache could not be verified.', ['exception_type' => $exception::class]);
            throw new AssistantUnavailable('The Assistant is temporarily unavailable. Your office records are unaffected. Try again shortly.');
        } finally {
            if ($lock && $acquired) {
                try {
                    $lock->release();
                } catch (Throwable $exception) {
                    Log::warning('Assistant guidance lock could not be released.', ['exception_type' => $exception::class]);
                }
            }
        }
    }

    private function cached(string $key, array $evidence): ?array
    {
        return $this->grounding->validate(Cache::get($key), $evidence['passages']);
    }

    private function result(array $validated, array $evidence, string $delivery, int $input = 0, int $output = 0): array
    {
        return $validated + ['input_tokens' => max(0, $input), 'output_tokens' => max(0, $output),
            'delivery' => $delivery, 'knowledge_version' => $evidence['version'],
            'evidence_characters' => $evidence['characters'], 'retrieved_passages' => count($evidence['passages'])];
    }

    private function fallback(array $evidence, string $language, int $input = 0, int $output = 0): array
    {
        $passage = $evidence['passages'][0] ?? null;
        if (! $passage) {
            return $this->result(['answer' => 'Verified guidance is not available for this task yet. Ask your agriculture office or system administrator.', 'sources' => []], $evidence, 'unavailable');
        }
        $prefix = $language === 'fil'
            ? 'Narito ang sinuring gabay (English).'
            : 'Here is the reviewed guidance.';

        return $this->result(['answer' => $prefix."\n\n".$passage['text'],
            'sources' => [['id' => $passage['id'], 'title' => $passage['title']]]], $evidence, 'guide', $input, $output);
    }
}
