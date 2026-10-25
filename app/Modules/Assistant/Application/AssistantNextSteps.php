<?php

namespace App\Modules\Assistant\Application;

use App\Models\User;
use App\Support\LocalTime;

/** Small, application-owned prompts; never inferred from reply text or records. */
final class AssistantNextSteps
{
    /** Application-owned messages, never fragments of the user's question. */
    private const AGRICULTURE_PROMPTS = [
        'agriculture-basics' => ['Farming basics', 'What is agriculture?'],
        'soil-health' => ['Soil health', 'How can I improve soil health?'],
        'crop-rotation' => ['Crop rotation', 'Explain crop rotation'],
        'crop-nutrition' => ['Crop nutrition', 'What is NPK fertilizer?'],
        'seed-selection' => ['Choosing seed', 'How do I choose good seeds?'],
        'crop-establishment' => ['Planting basics', 'How do I plant rice?'],
        'irrigation' => ['Water management', 'Explain irrigation'],
        'pest-management' => ['Pest management', 'What is integrated pest management?'],
        'livestock-care' => ['Livestock care', 'How do I care for farm animals?'],
        'aquaculture' => ['Fish farming', 'Explain aquaculture'],
    ];

    /** @return list<array{label:string,message:string}> */
    public function forReply(User $user, array $reply, array $references = [], ?string $clarificationKind = null, array $helpTopics = []): array
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
            if ($clarificationKind === 'agriculture') {
                $suggestions = [];
                foreach (array_slice($helpTopics, 0, 3) as $topic) {
                    if (! is_string($topic) || ! isset(self::AGRICULTURE_PROMPTS[$topic])
                        || ($veterinary && ! in_array($topic, OfficeAssistant::VETERINARY_TOPICS, true))) {
                        continue;
                    }
                    $suggestions[$topic] = $this->prompt(...self::AGRICULTURE_PROMPTS[$topic]);
                }
                if ($suggestions !== []) {
                    return array_values($suggestions);
                }
                if ($veterinary) {
                    return [$this->prompt(...self::AGRICULTURE_PROMPTS['livestock-care']),
                        $this->prompt('Animal Health help', 'How do I record animal services?')];
                }
            }
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
            'summary' => [$this->prompt('Registry sections', 'Summarize registry sections'),
                $this->prompt('Machinery inventory', 'Summarize machinery'),
                $this->prompt('Crop records', "Show our crops for {$year}")],
            'registry-sources', 'outside-lgu', 'delisted' => [$this->prompt('Outside LGU rows', 'Show outside LGU records'),
                $this->prompt('Delisted rows', 'Show delisted rows'),
                $this->prompt('How to review sections', 'How do I review registry sections?')],
            'cooperatives' => [$this->prompt('Review memberships', 'How do I manage cooperative membership?'),
                $this->prompt('LGU overview', 'Summarize our LGU records')],
            'machinery' => [$this->prompt('Equipment workflow', 'How do I track machinery maintenance?'),
                $this->prompt('LGU overview', 'Summarize our LGU records')],
            'lgu-profile' => [$this->prompt('Manage LGU settings', 'How do I manage LGU profiles?'),
                $this->prompt('LGU overview', 'Summarize our LGU records')],
            'crops' => array_merge($this->previousReport('crops', $year),
                [$this->prompt('Office harvests', "Show our harvests for {$year}"), $this->prompt('Crop drawing help', 'How do I draw crop sections?')]),
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
