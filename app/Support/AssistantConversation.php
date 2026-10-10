<?php

namespace App\Support;

use App\Exceptions\AssistantUnavailable;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Orchestrates one bounded read without disclosing operational text to AI. */
final class AssistantConversation
{
    public function __construct(
        private AssistantIntentResolver $resolver,
        private AssistantConversationContext $contexts,
        private AssistantRecords $records,
        private OfficeAssistant $assistant,
    ) {}

    public function answer(User $user, string $message, string $module, string $sessionId, ?string $contextToken = null, ?string $choiceToken = null): array
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

            return $this->clarification($user, $intent['clarification'], $contextToken);
        }
        if ($intent['task'] === 'help') {
            if ($user->isProvincialVeterinaryOffice() && ! in_array($intent['topic'], ['animals', 'access', 'help'], true)) {
                return $this->clarification($user, 'Your veterinary account can use Animal Health help and reports. Ask about recording animal services or your Animal Health summary.', $contextToken);
            }
            $result = $this->assistant->answerTopic($user, $intent['topic'], $intent['language'] ?? 'en');
            $references = ['task' => 'help', 'year' => $intent['year'], 'farmer_id' => $context['farmer_id'] ?? null];

            return [
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
            ];
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

        return $result;
    }

    private function token(User $user, string $sessionId, array $references): ?string
    {
        return $sessionId === '' ? null : $this->contexts->issue($user, $sessionId, $references);
    }

    private function clarification(User $user, string $answer, ?string $context): array
    {
        return ['answer' => $answer, 'source' => 'clarification', 'report' => 'clarification', 'year' => null,
            'scope' => $this->scope($user), 'actions' => [], 'links' => [], 'context' => $context,
            '_audit' => ['source' => 'clarification', 'topic' => 'clarification']];
    }

    private function scope(User $user): string
    {
        return mb_substr($user->scopeLabel(), 0, 180);
    }
}
