<?php

namespace App\Modules\Assistant\Application;

use App\Exceptions\AssistantUnavailable;
use App\Models\User;
use App\Modules\Assistant\Contracts\OfficeRecordReader;
use App\Support\LocalTime;
use Illuminate\Support\Facades\Gate;

/** Routes local conversation, scoped records and reviewed application guidance. */
final class AssistantConversation
{
    public function __construct(
        private AssistantIntentResolver $resolver,
        private AssistantConversationContext $contexts,
        private OfficeRecordReader $records,
        private OfficeAssistant $assistant,
        private AssistantIdentity $identity,
        private AssistantNextSteps $nextSteps,
    ) {}

    public function answer(User $user, string $message, string $module, string $sessionId, ?string $contextToken = null, ?string $choiceToken = null, string $guide = AssistantGuide::DEFAULT): array
    {
        Gate::forUser($user)->authorize('use', OfficeAssistant::class);
        if (! config('assistant.enabled')) {
            throw new AssistantUnavailable('The Assistant is not available yet. Ask your administrator to enable it.');
        }
        $context = $contextToken ? $this->contexts->read($user, $sessionId, $contextToken) : [];
        if ($context === null) {
            return $this->clarification($user, 'This conversation reference expired or your account area changed. Please restate your question or find the farmer again.', null);
        }
        $choice = $choiceToken ? $this->contexts->read($user, $sessionId, $choiceToken, 'choice') : null;
        if ($choiceToken && ($choice === null || empty($choice['farmer_id']))) {
            return $this->clarification($user, 'That farmer choice expired or is unavailable. Please search for the farmer again.', null);
        }
        if (! $choiceToken && preg_match('/^[1-6]$/', trim($message)) && ! empty($context['choices'])) {
            $id = $context['choices'][(int) trim($message) - 1] ?? null;
            if ($id === null) {
                return $this->clarification($user, 'Choose one of the farmer matches in the previous reply.', $contextToken);
            }
            $choice = ['task' => 'farmer', 'year' => $context['year'], 'farmer_id' => $id];
        }
        $intent = $choice ? ['task' => 'farmer', 'year' => $choice['year'], 'farmer_id' => $choice['farmer_id']]
            : $this->resolver->resolve($message, $context, $module, LocalTime::now()->year);
        if ($intent['task'] === 'identity') {
            // Polite interludes should not discard a validated report/help
            // reference. They still carry no prior reply text into inference.
            $replyContext = in_array($intent['kind'], ['greeting', 'thanks', 'context', 'account'], true) && $context !== []
                ? $contextToken
                : $this->token($user, $sessionId, ['task' => 'identity', 'year' => $intent['year'], 'farmer_id' => $context['farmer_id'] ?? null]);

            return $this->withNextSteps($user, [
                'answer' => $this->identity->answer($user, $intent['kind'], $intent['language'] ?? 'en', $module, $guide),
                'source' => 'conversation', 'report' => 'identity', 'year' => null,
                'scope' => $this->scope($user), 'actions' => [], 'links' => [],
                'context' => $replyContext,
                '_audit' => ['source' => 'conversation', 'topic' => $intent['kind']],
            ]);
        }
        if ($intent['task'] === 'clarification') {
            if ($intent['reset_context'] ?? false) {
                $contextToken = null;
            }
            if (isset($intent['references'])) {
                if ($user->isProvincialVeterinaryOffice() && ! in_array($intent['references']['task'], ['animal-health', 'summary'], true)) {
                    return $this->clarification($user, 'Your veterinary account can view Animal Health reports only. Ask for your Animal Health summary and one reporting year.', $contextToken);
                }
                // These are validated task/period hints, never a saved question or
                // permission grant. The resolved report reauthorizes next turn.
                $contextToken = $this->token($user, $sessionId, $intent['references']);
            }

            return $this->clarification($user, $intent['clarification'], $contextToken, $intent['references'] ?? [], $intent['clarification_kind'] ?? null);
        }
        if ($intent['task'] === 'help') {
            if ($user->isProvincialVeterinaryOffice() && ! in_array($intent['topic'], OfficeAssistant::VETERINARY_TOPICS, true)) {
                return $this->clarification($user, 'Your veterinary account can use Animal Health help and reports. Ask about recording animal services or your Animal Health summary.', $contextToken);
            }
            $result = $this->assistant->answerTopic($user, $intent['topic'], $intent['language'] ?? 'en', $intent['style'] ?? 'plain');
            $references = ['task' => 'help', 'year' => $intent['year'], 'farmer_id' => $context['farmer_id'] ?? null,
                'help_topic' => $intent['topic'], 'help_style' => $intent['style'] ?? 'plain', 'help_language' => $intent['language'] ?? 'en'];

            return $this->withNextSteps($user, [
                'answer' => $result['answer'], 'source' => 'help', 'report' => $intent['topic'], 'year' => null,
                'scope' => $this->scope($user), 'actions' => [], 'links' => [],
                'sources' => $result['sources'], 'delivery' => $result['delivery'],
                'context' => $this->token($user, $sessionId, $references),
                // Count fields avoid credential-token names, which the audit
                // sanitizer deliberately removes. These contain integers only.
                '_audit' => ['source' => 'help', 'topic' => $intent['topic'], 'provider_input_count' => $result['input_tokens'], 'provider_output_count' => $result['output_tokens'],
                    'delivery' => $result['delivery'], 'knowledge_version' => $result['knowledge_version'],
                    'retrieved_passages' => $result['retrieved_passages'], 'cited_passages' => count($result['sources']),
                    'evidence_characters' => $result['evidence_characters']],
            ], $references);
        }
        if ($user->isProvincialVeterinaryOffice() && ! in_array($intent['task'], ['animal-health', 'summary'], true)) {
            return $this->clarification($user, 'Your veterinary account can view Animal Health reports only. Ask for your Animal Health summary and reporting year.', $contextToken);
        }
        $result = $this->records->answerTask($user, $intent['task'], $intent['year'], $intent['search'] ?? '', $intent['farmer_id'] ?? null);
        if (mb_strlen($result['answer']) > 6000) {
            throw new AssistantUnavailable('That summary is too long to display. Open the register for the full report.');
        }
        $actions = [];
        $ids = [];
        foreach (array_slice($result['actions'], 0, 6) as $action) {
            $id = (int) $action['farmer_id'];
            $ids[] = $id;
            if ($sessionId !== '') {
                $actions[] = ['label' => $action['label'], 'choice' => $this->contexts->issue($user, $sessionId,
                    ['task' => 'farmer', 'year' => $result['year'], 'farmer_id' => $id], 'choice')];
            }
        }
        $references = [
            'task' => $result['report'], 'year' => $result['year'],
            'farmer_id' => $result['_farmer_id'] ?? $intent['farmer_id'] ?? null, 'choices' => $ids,
        ];
        unset($result['_farmer_id']);
        $result['actions'] = $actions;
        $result['scope'] = $this->scope($user);
        $result['context'] = $this->token($user, $sessionId, $references);
        $result['_audit'] = ['source' => 'records', 'report' => $result['report'], 'year' => $result['year'], 'returned_matches' => count($actions)];

        return $this->withNextSteps($user, $result, $references);
    }

    private function token(User $user, string $sessionId, array $references): ?string
    {
        return $sessionId === '' ? null : $this->contexts->issue($user, $sessionId, $references);
    }

    private function clarification(User $user, string $answer, ?string $context, array $references = [], ?string $kind = null): array
    {
        return $this->withNextSteps($user, ['answer' => $answer, 'source' => 'clarification', 'report' => 'clarification', 'year' => null,
            'scope' => $this->scope($user), 'actions' => [], 'links' => [], 'context' => $context,
            '_audit' => ['source' => 'clarification', 'topic' => 'clarification']], $references, $kind);
    }

    private function withNextSteps(User $user, array $reply, array $references = [], ?string $kind = null): array
    {
        $reply['suggestions'] = $this->nextSteps->forReply($user, $reply, $references, $kind);

        return $reply;
    }

    private function scope(User $user): string
    {
        return mb_substr($user->scopeLabel(), 0, 180);
    }
}
