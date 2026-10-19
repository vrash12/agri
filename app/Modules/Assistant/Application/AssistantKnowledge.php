<?php

namespace App\Modules\Assistant\Application;

use App\Exceptions\AssistantUnavailable;
use App\Models\User;

/** Retrieves reviewed application help only; operational data never enters this index. */
final class AssistantKnowledge
{
    public const VETERINARY_QUESTION = 'Explain this veterinary role\'s Animal Health-only access and supported animal-service recording workflows.';

    private const MAXIMUM_PASSAGES = 3;

    private const MAXIMUM_PASSAGE_CHARACTERS = 2400;

    private const MAXIMUM_PASSAGE_BYTES = 9600;

    private const MAXIMUM_CORPUS_BYTES = 81920;

    private const MAXIMUM_EVIDENCE_CHARACTERS = 7000;

    /**
     * This manifest is the sole ingestion boundary. No glob, caller path, upload,
     * general repository documentation or record is an eligible knowledge source.
     *
     * @var array<string, array{title: string, topics: list<string>, primary: list<string>, audience: string}>
     */
    private const MANIFEST = [
        'crop-sections' => ['title' => 'Drawing planted crop sections', 'topics' => ['crop-drawing'], 'primary' => ['crop-drawing'], 'audience' => 'office'],
        'crop-boundary-rules' => ['title' => 'Crop boundary and saving rules', 'topics' => ['crop-drawing'], 'primary' => [], 'audience' => 'office'],
        'parcel-boundaries' => ['title' => 'Drawing or importing a farm parcel', 'topics' => ['parcel-drawing'], 'primary' => ['parcel-drawing'], 'audience' => 'office'],
        'parcel-reference-limits' => ['title' => 'Parcel and municipality reference limits', 'topics' => ['parcel-drawing'], 'primary' => [], 'audience' => 'office'],
        'rice-release-import' => ['title' => 'Importing rice seed releases', 'topics' => ['seed-import'], 'primary' => ['seed-import'], 'audience' => 'office'],
        'assistance-units' => ['title' => 'Assistance and harvest quantities', 'topics' => ['seed-import', 'harvest-entry'], 'primary' => [], 'audience' => 'office'],
        'satellite-field-check' => ['title' => 'Using the satellite field check', 'topics' => ['ndvi', 'ndmi', 'savi', 'evi', 'satellite-indices'], 'primary' => ['ndvi', 'ndmi', 'savi', 'evi', 'satellite-indices'], 'audience' => 'office'],
        'satellite-interpretation' => ['title' => 'Satellite coverage and interpretation limits', 'topics' => ['ndvi', 'ndmi', 'savi', 'evi', 'satellite-indices'], 'primary' => [], 'audience' => 'office'],
        'climate-readiness' => ['title' => 'El Niño readiness and climate evidence', 'topics' => ['climate-readiness'], 'primary' => ['climate-readiness'], 'audience' => 'office'],
        'animal-services' => ['title' => 'Recording multiple animals and services', 'topics' => ['animals'], 'primary' => ['animals'], 'audience' => 'all'],
        'animal-safety' => ['title' => 'Animal Health advice and record limits', 'topics' => ['animals'], 'primary' => [], 'audience' => 'all'],
        'office-access' => ['title' => 'Office role and location access', 'topics' => ['access', 'help'], 'primary' => ['access'], 'audience' => 'office'],
        'veterinary-access' => ['title' => 'Veterinary account access', 'topics' => ['access', 'help'], 'primary' => ['access'], 'audience' => 'veterinary'],
        'farmer-portal-access' => ['title' => 'Farmer portal sign-in and assisted recovery', 'topics' => ['farmer-login'], 'primary' => ['farmer-login'], 'audience' => 'office'],
        'farmer-registry-card' => ['title' => 'Farmer registry card printing and signatures', 'topics' => ['farmer-id'], 'primary' => ['farmer-id'], 'audience' => 'office'],
        'harvest-records' => ['title' => 'Entering harvest records', 'topics' => ['harvest-entry'], 'primary' => ['harvest-entry'], 'audience' => 'office'],
        'office-assistant-help' => ['title' => 'Supported Assistant questions', 'topics' => ['help'], 'primary' => ['help'], 'audience' => 'office'],
        'veterinary-assistant-help' => ['title' => 'Assistant help for Animal Health', 'topics' => ['help'], 'primary' => ['help'], 'audience' => 'veterinary'],
        'dashboard-overview' => ['title' => 'Reading dashboard figures and reports', 'topics' => ['dashboard-overview'], 'primary' => ['dashboard-overview'], 'audience' => 'office'],
        'municipality-weather' => ['title' => 'Municipality weather and official bulletins', 'topics' => ['weather'], 'primary' => ['weather'], 'audience' => 'office'],
        'cooperative-membership' => ['title' => 'Cooperative profiles and member lists', 'topics' => ['cooperatives'], 'primary' => ['cooperatives'], 'audience' => 'office'],
        'machinery-inventory' => ['title' => 'Equipment assignment and maintenance', 'topics' => ['machinery'], 'primary' => ['machinery'], 'audience' => 'office'],
        'backup-folder' => ['title' => 'Protected Backup Folder files', 'topics' => ['backups'], 'primary' => ['backups'], 'audience' => 'office'],
        'audit-trail' => ['title' => 'Scoped audit history and exports', 'topics' => ['audit'], 'primary' => ['audit'], 'audience' => 'office'],
        'municipality-geofences' => ['title' => 'Municipality geofence references', 'topics' => ['geofences'], 'primary' => ['geofences'], 'audience' => 'office'],
        'system-overview' => ['title' => 'eAgriLGU system overview', 'topics' => ['system-overview'], 'primary' => ['system-overview'], 'audience' => 'office'],
        'assistant-ai-technology' => ['title' => 'Assistant AI technology and retrieval', 'topics' => ['ai-technology'], 'primary' => ['ai-technology'], 'audience' => 'all'],
        'assistant-identity-privacy' => ['title' => 'Assistant identity and privacy boundaries', 'topics' => ['assistant-privacy', 'ai-technology'], 'primary' => ['assistant-privacy'], 'audience' => 'all'],
        'farmer-data-quality' => ['title' => 'Missing information and duplicate review', 'topics' => ['data-quality'], 'primary' => ['data-quality'], 'audience' => 'office'],
        'farmer-registry' => ['title' => 'Farmer profiles and workbook imports', 'topics' => ['farmer-registry'], 'primary' => ['farmer-registry'], 'audience' => 'office'],
        'assistance-management' => ['title' => 'Agriculture and fisheries releases and rice sheets', 'topics' => ['assistance-management'], 'primary' => ['assistance-management'], 'audience' => 'office'],
        'assistance-coverage' => ['title' => 'Recorded assistance coverage by municipality', 'topics' => ['assistance-coverage'], 'primary' => ['assistance-coverage'], 'audience' => 'office'],
    ];

    private const OFFICE_ROLES = [
        User::ROLE_SYSTEM_OWNER, User::ROLE_REGIONAL_HEAD, User::ROLE_SUPER_ADMIN,
        User::ROLE_PROVINCIAL_STAFF, User::ROLE_MUNICIPAL_HEAD, User::ROLE_MUNICIPAL_STAFF,
    ];

    private const STOP_WORDS = ['a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'do', 'does', 'for', 'from', 'how', 'in', 'inside', 'is', 'it', 'its', 'of', 'on', 'or', 'the', 'their', 'this', 'to', 'what', 'with', 'one', 'authorized', 'office', 'staff'];

    private ?string $indexVersion = null;

    /** @var list<array{id: string, title: string, text: string, topics: list<string>, primary: list<string>, audience: string, terms: array<string, int>, length: int}> */
    private array $index = [];

    /**
     * Callers authorize OfficeAssistant before retrieval. This extra role gate
     * filters source eligibility before lexical ranking, including its statistics.
     *
     * @return array{version: string, passages: list<array{id: string, title: string, text: string}>, characters: int}
     */
    public function retrieve(User $user, string $topic, string $query): array
    {
        $veterinary = $user->isProvincialVeterinaryOffice();
        if (! $user->isActive() || $user->isVisitor() || $user->isGisEvaluator()
            || (! $veterinary && ! in_array($user->role, self::OFFICE_ROLES, true))
            || ! isset(OfficeAssistant::TOPICS[$topic])
            || ($veterinary && ! in_array($topic, OfficeAssistant::VETERINARY_TOPICS, true))) {
            throw $this->unavailable();
        }
        $canonical = OfficeAssistant::TOPICS[$topic]['question'];
        if ($query !== $canonical && ! ($veterinary && in_array($topic, ['access', 'help'], true)
            && $query === self::VETERINARY_QUESTION)) {
            throw $this->unavailable();
        }

        $this->loadIndex();
        $eligible = array_values(array_filter($this->index, static fn (array $passage): bool => in_array($topic, $passage['topics'], true)
            && ($passage['audience'] === 'all' || $passage['audience'] === ($veterinary ? 'veterinary' : 'office'))));
        if ($eligible === []) {
            throw $this->unavailable();
        }
        $ranked = $this->rank($eligible, $this->tokens($query), $topic);
        $passages = [];
        $characters = 0;
        foreach ($ranked as $passage) {
            $size = mb_strlen($passage['text']);
            if ($passage['score'] <= 0 || $characters + $size > self::MAXIMUM_EVIDENCE_CHARACTERS) {
                continue;
            }
            $passages[] = ['id' => $passage['id'], 'title' => $passage['title'], 'text' => $passage['text']];
            $characters += $size;
            if (count($passages) === self::MAXIMUM_PASSAGES) {
                break;
            }
        }
        // A related safety chunk alone cannot establish the requested workflow.
        if ($passages === [] || ! $ranked[0]['is_primary'] || $ranked[0]['score'] <= 0) {
            throw $this->unavailable();
        }

        return ['version' => $this->indexVersion, 'passages' => $passages, 'characters' => $characters];
    }

    private function loadIndex(): void
    {
        $directory = app('path.resources').DIRECTORY_SEPARATOR.'assistant'.DIRECTORY_SEPARATOR.'knowledge';
        $realDirectory = realpath($directory);
        if ($realDirectory === false || ! is_dir($realDirectory) || is_link($directory)
            || count(self::MANIFEST) > 32) {
            throw $this->unavailable();
        }
        $contents = [];
        $bytes = 0;
        $digest = hash_init('sha256');
        hash_update($digest, 'assistant-knowledge-bm25-v1'.json_encode(self::MANIFEST, JSON_THROW_ON_ERROR));
        foreach (self::MANIFEST as $id => $metadata) {
            $path = $directory.DIRECTORY_SEPARATOR.$id.'.md';
            $resolved = realpath($path);
            if ($resolved === false || ! is_file($resolved) || is_link($path)
                || dirname($resolved) !== $realDirectory) {
                throw $this->unavailable();
            }
            $raw = @file_get_contents($resolved, false, null, 0, self::MAXIMUM_PASSAGE_BYTES + 1);
            if (! is_string($raw) || strlen($raw) > self::MAXIMUM_PASSAGE_BYTES
                || ! mb_check_encoding($raw, 'UTF-8')) {
                throw $this->unavailable();
            }
            $text = trim($raw);
            $bytes += strlen($raw);
            if ($text === '' || mb_strlen($text) > self::MAXIMUM_PASSAGE_CHARACTERS
                || $bytes > self::MAXIMUM_CORPUS_BYTES) {
                throw $this->unavailable();
            }
            $contents[$id] = $text;
            hash_update($digest, $id."\0".$raw."\0");
        }
        $version = hash_final($digest);
        if ($version === $this->indexVersion) {
            return;
        }

        $index = [];
        foreach (self::MANIFEST as $id => $metadata) {
            $terms = $this->tokens($contents[$id]);
            $index[] = ['id' => $id, 'text' => $contents[$id], ...$metadata,
                'terms' => $terms, 'length' => array_sum($terms)];
        }
        $this->index = $index;
        $this->indexVersion = $version;
    }

    /** @return array<string, int> */
    private function tokens(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($text), $matches);
        $tokens = [];
        foreach ($matches[0] as $word) {
            if (mb_strlen($word) > 1 && ! in_array($word, self::STOP_WORDS, true)) {
                $tokens[$word] = ($tokens[$word] ?? 0) + 1;
            }
        }

        return $tokens;
    }

    /** @return list<array<string, mixed>> */
    private function rank(array $passages, array $query, string $topic): array
    {
        $count = count($passages);
        $averageLength = max(1, array_sum(array_column($passages, 'length')) / $count);
        $frequency = [];
        foreach (array_keys($query) as $term) {
            $frequency[$term] = count(array_filter($passages, static fn (array $passage): bool => isset($passage['terms'][$term])));
        }
        foreach ($passages as &$passage) {
            $score = 0.0;
            foreach (array_keys($query) as $term) {
                $termFrequency = $passage['terms'][$term] ?? 0;
                if ($termFrequency === 0) {
                    continue;
                }
                $inverseFrequency = log(1 + ($count - $frequency[$term] + 0.5) / ($frequency[$term] + 0.5));
                $score += $inverseFrequency * ($termFrequency * 2.2)
                    / ($termFrequency + 1.2 * (0.25 + 0.75 * $passage['length'] / $averageLength));
            }
            $passage['score'] = $score;
            $passage['is_primary'] = in_array($topic, $passage['primary'], true);
        }
        unset($passage);
        usort($passages, static fn (array $first, array $second): int => ((int) $second['is_primary'] <=> (int) $first['is_primary'])
            ?: ($second['score'] <=> $first['score']) ?: strcmp($first['id'], $second['id']));

        return $passages;
    }

    private function unavailable(): AssistantUnavailable
    {
        return new AssistantUnavailable('Verified workflow help is unavailable for this question. Ask your office administrator to check the Assistant knowledge files.');
    }
}
