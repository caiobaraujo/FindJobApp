<?php

namespace App\Services;

class JobLeadMatchAnalyzer
{
    public function __construct(
        private readonly ResumeDiscoverySignalBuilder $resumeDiscoverySignalBuilder,
        private readonly TechnicalKeywordSignalQuality $technicalKeywordSignalQuality,
    ) {
    }

    /**
     * @param list<string> $jobKeywords
     * @param list<string> $coreSkills
     * @return array{matched_keywords: list<string>, missing_keywords: list<string>, match_summary: string}
     */
    public function analyze(array $jobKeywords, ?string $baseResumeText, array $coreSkills): array
    {
        $normalizedJobKeywords = array_values(array_unique(array_filter(
            array_map(fn (string $keyword): ?string => $this->normalizeKeyword($keyword), $jobKeywords),
        )));

        if ($normalizedJobKeywords === []) {
            return [
                'matched_keywords' => [],
                'missing_keywords' => [],
                'match_summary' => 'No job keywords are available yet for matching.',
            ];
        }

        $keywordQuality = $this->technicalKeywordSignalQuality->summarize($normalizedJobKeywords);
        $explainableJobKeywords = $keywordQuality['explainable_keywords'];

        if ($explainableJobKeywords === []) {
            return [
                'matched_keywords' => [],
                'missing_keywords' => [],
                'match_summary' => 'No strong technical job keywords are available yet for matching.',
            ];
        }

        $resumeSignals = $this->resumeDiscoverySignalBuilder->matchSignals($baseResumeText, $coreSkills);

        if ($resumeSignals === []) {
            return [
                'matched_keywords' => [],
                'missing_keywords' => $explainableJobKeywords,
                'match_summary' => $this->matchSummary([], $explainableJobKeywords),
            ];
        }

        $matchedKeywords = [];
        $missingKeywords = [];

        foreach ($explainableJobKeywords as $keyword) {
            if (in_array($keyword, $resumeSignals, true)) {
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

    private function normalizeKeyword(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $normalizedText = strtolower(trim($text));
        $canonicalKeyword = TechnicalKeywordTaxonomy::canonicalForExplicitSkill($normalizedText);

        if ($canonicalKeyword !== null) {
            return $canonicalKeyword;
        }

        $normalizedText = preg_replace('/[^a-z0-9]+/', '_', $normalizedText) ?? '';
        $normalizedText = trim(preg_replace('/_+/', '_', $normalizedText) ?? '', '_');

        if ($normalizedText === '') {
            return null;
        }

        return $normalizedText;
    }
}
