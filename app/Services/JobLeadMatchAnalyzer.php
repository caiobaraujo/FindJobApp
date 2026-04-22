<?php

namespace App\Services;

class JobLeadMatchAnalyzer
{
    /**
     * @param list<string> $jobKeywords
     * @param list<string> $coreSkills
     * @return array{matched_keywords: list<string>, missing_keywords: list<string>, match_summary: string}
     */
    public function analyze(array $jobKeywords, ?string $baseResumeText, array $coreSkills): array
    {
        $normalizedJobKeywords = array_values(array_unique(array_filter(
            array_map(fn (string $keyword): ?string => $this->normalizeText($keyword), $jobKeywords),
        )));

        if ($normalizedJobKeywords === []) {
            return [
                'matched_keywords' => [],
                'missing_keywords' => [],
                'match_summary' => 'No job keywords are available yet for matching.',
            ];
        }

        $profileCorpus = $this->profileCorpus($baseResumeText, $coreSkills);
        $matchedKeywords = [];
        $missingKeywords = [];

        foreach ($normalizedJobKeywords as $keyword) {
            if ($this->matchesKeyword($keyword, $profileCorpus)) {
                $matchedKeywords[] = $keyword;
                continue;
            }

            $missingKeywords[] = $keyword;
        }

        return [
            'matched_keywords' => $matchedKeywords,
            'missing_keywords' => $missingKeywords,
            'match_summary' => $this->matchSummary($matchedKeywords, $missingKeywords),
        ];
    }

    /**
     * @param list<string> $coreSkills
     */
    private function profileCorpus(?string $baseResumeText, array $coreSkills): string
    {
        return trim(implode(' ', array_filter([
            $this->normalizeText($baseResumeText),
            $this->normalizeText(implode(' ', $coreSkills)),
        ])));
    }

    private function matchesKeyword(string $keyword, string $profileCorpus): bool
    {
        if ($profileCorpus === '') {
            return false;
        }

        if (str_contains($profileCorpus, $keyword)) {
            return true;
        }

        $tokens = explode(' ', $keyword);

        foreach ($tokens as $token) {
            if (! str_contains($profileCorpus, $token)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $matchedKeywords
     * @param list<string> $missingKeywords
     */
    private function matchSummary(array $matchedKeywords, array $missingKeywords): string
    {
        return sprintf(
            'Matched %d keyword%s and missing %d.',
            count($matchedKeywords),
            count($matchedKeywords) === 1 ? '' : 's',
            count($missingKeywords),
        );
    }

    private function normalizeText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $normalizedText = strtolower(trim($text));
        $normalizedText = preg_replace('/[^a-z0-9\s]+/', ' ', $normalizedText) ?? '';
        $normalizedText = trim(preg_replace('/\s+/', ' ', $normalizedText) ?? '');

        if ($normalizedText === '') {
            return null;
        }

        return $normalizedText;
    }
}
