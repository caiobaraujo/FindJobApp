<?php

namespace App\Services;

class JobLeadKeywordExtractor
{
    /**
     * @var list<string>
     */
    private const IMPORTANT_TERMS = [
        'php',
        'laravel',
        'vue',
        'react',
        'typescript',
        'javascript',
        'mysql',
        'postgresql',
        'sql',
        'aws',
        'docker',
        'kubernetes',
        'api',
        'testing',
        'automation',
        'analytics',
        'leadership',
        'communication',
        'product',
        'design',
        'python',
    ];

    /**
     * @var array<string, true>
     */
    private const STOPWORDS = [
        'a' => true,
        'an' => true,
        'and' => true,
        'are' => true,
        'as' => true,
        'at' => true,
        'be' => true,
        'but' => true,
        'by' => true,
        'for' => true,
        'from' => true,
        'if' => true,
        'in' => true,
        'into' => true,
        'is' => true,
        'it' => true,
        'of' => true,
        'on' => true,
        'or' => true,
        'our' => true,
        'that' => true,
        'the' => true,
        'their' => true,
        'this' => true,
        'to' => true,
        'using' => true,
        'we' => true,
        'will' => true,
        'with' => true,
        'you' => true,
        'your' => true,
    ];

    /**
     * @return array{extracted_keywords: list<string>, ats_hints: list<string>}
     */
    public function analyze(?string $descriptionText): array
    {
        $normalizedDescriptionText = $this->normalizeDescriptionText($descriptionText);

        if ($normalizedDescriptionText === null) {
            return [
                'extracted_keywords' => [],
                'ats_hints' => ['Paste the full job description to unlock ATS keyword analysis.'],
            ];
        }

        $keywords = $this->extractKeywords($normalizedDescriptionText);

        return [
            'extracted_keywords' => $keywords,
            'ats_hints' => $this->buildAtsHints($normalizedDescriptionText, $keywords),
        ];
    }

    /**
     * @return list<string>
     */
    public function extractKeywords(?string $descriptionText): array
    {
        $normalizedDescriptionText = $this->normalizeDescriptionText($descriptionText);

        if ($normalizedDescriptionText === null) {
            return [];
        }

        $words = $this->filteredWords($normalizedDescriptionText);
        $wordCounts = $this->counts($words);
        $phraseCounts = $this->counts($this->phrases($words));

        $keywords = array_merge(
            $this->sortedCandidates($phraseCounts, 2),
            $this->sortedCandidates($wordCounts, 1),
        );

        return array_slice(array_values(array_unique($keywords)), 0, 8);
    }

    private function normalizeDescriptionText(?string $descriptionText): ?string
    {
        if ($descriptionText === null) {
            return null;
        }

        $normalizedDescriptionText = trim(preg_replace('/\s+/', ' ', $descriptionText) ?? '');

        if ($normalizedDescriptionText === '') {
            return null;
        }

        return $normalizedDescriptionText;
    }

    /**
     * @return list<string>
     */
    private function filteredWords(string $descriptionText): array
    {
        preg_match_all('/[A-Za-z][A-Za-z0-9+#.-]*/', strtolower($descriptionText), $matches);

        return array_values(array_filter(
            $matches[0],
            fn (string $word): bool => strlen($word) >= 3 && ! isset(self::STOPWORDS[$word]),
        ));
    }

    /**
     * @param list<string> $items
     * @return array<string, int>
     */
    private function counts(array $items): array
    {
        $counts = [];

        foreach ($items as $item) {
            $counts[$item] = ($counts[$item] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param list<string> $words
     * @return list<string>
     */
    private function phrases(array $words): array
    {
        $phrases = [];

        for ($index = 0; $index < count($words) - 1; $index++) {
            $phrases[] = $words[$index].' '.$words[$index + 1];
        }

        return $phrases;
    }

    /**
     * @param array<string, int> $counts
     * @return list<string>
     */
    private function sortedCandidates(array $counts, int $minimumCount): array
    {
        $candidates = array_filter(
            $counts,
            fn (int $count, string $keyword): bool => $count >= $minimumCount && strlen($keyword) >= 3,
            ARRAY_FILTER_USE_BOTH,
        );

        uksort($candidates, fn (string $left, string $right): int => $this->compareCandidates(
            $left,
            $right,
            $candidates,
        ));

        return array_keys($candidates);
    }

    /**
     * @param array<string, int> $counts
     */
    private function compareCandidates(string $left, string $right, array $counts): int
    {
        $countComparison = $counts[$right] <=> $counts[$left];

        if ($countComparison !== 0) {
            return $countComparison;
        }

        $lengthComparison = strlen($right) <=> strlen($left);

        if ($lengthComparison !== 0) {
            return $lengthComparison;
        }

        return strcmp($left, $right);
    }

    /**
     * @param list<string> $keywords
     * @return list<string>
     */
    private function buildAtsHints(string $descriptionText, array $keywords): array
    {
        $hints = [];

        if (count($keywords) < 4) {
            $hints[] = 'Paste more of the job description to surface a stronger ATS keyword set.';
        }

        if (strlen($descriptionText) < 400) {
            $hints[] = 'The description looks short. Add the full posting before tailoring your resume.';
        }

        $importantTerms = array_values(array_filter(
            self::IMPORTANT_TERMS,
            fn (string $term): bool => str_contains(strtolower($descriptionText), $term),
        ));

        if ($importantTerms !== []) {
            $hints[] = 'Likely ATS terms to reflect in your resume: '.implode(', ', array_slice($importantTerms, 0, 6)).'.';
        }

        if ($hints === []) {
            $hints[] = 'Review the extracted keywords and mirror the strongest matching terms in your resume bullets.';
        }

        return $hints;
    }
}
