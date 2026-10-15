<?php

namespace App\Modules\Assistant\Application;

/** Validates structure and source membership; this is not semantic entailment. */
final class AssistantGroundedAnswer
{
    /**
     * @param  list<array{id:string,title:string,text:string}>  $passages
     * @return array{answer:string,sources:list<array{id:string,title:string}>}|null
     */
    public function validate(mixed $output, array $passages): ?array
    {
        if (! is_array($output) || array_diff(array_keys($output), ['answer', 'supported', 'citations'])
            || ($output['supported'] ?? null) !== true
            || ! is_string($output['answer'] ?? null) || ! is_array($output['citations'] ?? null)) {
            return null;
        }
        $answer = trim($output['answer']);
        $citations = $output['citations'];
        if ($answer === '' || mb_strlen($answer) > 3000 || ! array_is_list($citations)
            || count($citations) < 1 || count($citations) > 3
            || preg_match('/https?:\/\/|www\.|<[^>]+>|[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]|sk-[a-z0-9_-]{12,}|-----BEGIN[^\n]*PRIVATE KEY|AGRI-F-\d+|[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}|\b(?:\+?63|0)9\d{9}\b/iu', $answer)) {
            return null;
        }
        $byId = array_column($passages, null, 'id');
        $sources = [];
        foreach ($citations as $id) {
            if (! is_string($id) || ! isset($byId[$id]) || isset($sources[$id])) {
                return null;
            }
            $sources[$id] = ['id' => $id, 'title' => $byId[$id]['title']];
        }

        return ['answer' => $answer, 'sources' => array_values($sources)];
    }
}
