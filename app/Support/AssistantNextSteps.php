<?php

namespace App\Support;

use App\Models\User;

/** Small, application-owned prompts; never inferred from reply text or records. */
final class AssistantNextSteps
{
    /** @return list<array{label:string,message:string}> */
    public function forReply(User $user, array $reply, array $references = [], ?string $clarificationKind = null): array
    {
        $veterinary = $user->isProvincialVeterinaryOffice();
        if ($reply['source'] === 'help') {
            return [
                ($references['help_style'] ?? null) === 'steps'
                    ? $this->prompt('Give an example', 'Give me an example')
                    : $this->prompt('Show the steps', 'Show me the steps'),
                $this->prompt('Make it shorter', 'Make it shorter'),
                ($references['help_language'] ?? 'en') === 'fil'
                    ? $this->prompt('In English', 'In English')
                    : $this->prompt('In Filipino', 'In Filipino'),
            ];
        }
        if ($reply['source'] === 'clarification') {
            if ($clarificationKind === 'scope' && ! $veterinary) {
                return [$this->prompt('Selected farmer', 'Selected farmer'), $this->prompt('Whole office', 'Whole office')];
            }
            if ($clarificationKind === 'year') {
                return $this->years((int) ($references['year'] ?? LocalTime::now()->year));
            }

            return [$this->prompt('What can you help with?', 'What can you do?'),
                $this->prompt('Explain this page', 'How do I use this page?')];
        }
        if ($reply['source'] === 'conversation') {
            return [$this->prompt('Explain this page', 'How do I use this page?'),
                $veterinary ? $this->prompt('Animal Health summary', 'Summarize Animal Health services')
                    : $this->prompt('Office summary', 'Summarize our workspace'),
                $this->prompt('How is data protected?', 'How does the Assistant protect farmer data?')];
        }
        if ($reply['source'] !== 'records') {
            return [];
        }
        $report = $reply['report'];
        $year = (int) $reply['year'];
        if ($report === 'farmer-search') {
            // Farmer matches already have their own signed choice buttons.
            return [];
        }
        if ($veterinary) {
            return array_merge($this->previousReport('animal-health', $year),
                [$this->prompt('Record an animal service', 'How do I record animal services?')]);
        }
        if ($report === 'data-quality') {
            return [$this->prompt('How to review quality', 'How do I review data quality?'),
                $this->prompt('Farmer summary', 'Summarize our farmers'),
                $this->prompt('Workspace summary', 'Summarize our workspace')];
        }
        if (in_array($report, ['farmer', 'farmer-parcels', 'farmer-assistance', 'farmer-harvests'], true) && ! empty($references['farmer_id'])) {
            return match ($report) {
                'farmer-assistance' => [$this->prompt('Their parcels and crops', "Show their crops for {$year}"),
                    $this->prompt('Their harvests', "Show their harvests for {$year}"),
                    $this->prompt('Whole-office assistance', "Show our assistance for {$year}")],
                'farmer-harvests' => [$this->prompt('Their parcels and crops', "Show their crops for {$year}"),
                    $this->prompt('Their assistance', "Show their assistance for {$year}"),
                    $this->prompt('Whole-office harvests', "Show our harvests for {$year}")],
                default => [$this->prompt('Their parcels and crops', "Show their crops for {$year}"),
                    $this->prompt('Their assistance', "Show their assistance for {$year}"),
                    $this->prompt('Their harvests', "Show their harvests for {$year}")],
            };
        }

        return match ($report) {
            'assistance' => array_merge($this->previousReport($report, $year),
                [$this->prompt('Office harvests', "Show our harvests for {$year}"), $this->prompt('How to record assistance', 'How do I record assistance releases?')]),
            'harvests' => array_merge($this->previousReport($report, $year),
                [$this->prompt('Office assistance', "Show our assistance for {$year}"), $this->prompt('How to record harvests', 'How do I record harvests?')]),
            'animal-health' => array_merge($this->previousReport($report, $year),
                [$this->prompt('Record an animal service', 'How do I record animal services?')]),
            default => [$this->prompt('Office assistance', "Show our assistance for {$year}"),
                $this->prompt('Office harvests', "Show our harvests for {$year}"),
                $this->prompt('Find a farmer', 'How do I use the farmer registry?')],
        };
    }

    /** @return list<array{label:string,message:string}> */
    private function years(int $year): array
    {
        $steps = [$this->prompt("Use {$year}", (string) $year)];
        if ($year > 1900) {
            $steps[] = $this->prompt('Use '.($year - 1), (string) ($year - 1));
        }

        return $steps;
    }

    /** @return list<array{label:string,message:string}> */
    private function previousReport(string $report, int $year): array
    {
        if ($year <= 1900) {
            return [];
        }
        $message = $report === 'animal-health' ? 'Summarize Animal Health services for ' : "Show our {$report} for ";

        return [$this->prompt('View '.($year - 1), $message.($year - 1))];
    }

    /** @return array{label:string,message:string} */
    private function prompt(string $label, string $message): array
    {
        return ['label' => $label, 'message' => $message];
    }
}
