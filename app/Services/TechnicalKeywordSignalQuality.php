<?php

namespace App\Services;

class TechnicalKeywordSignalQuality
{
    public const QUALITY_MISSING = 'missing';

    public const QUALITY_LIMITED = 'limited';

    public const QUALITY_STRONG = 'strong';

    private const CONTEXTUAL_KEYWORDS = [
        'api' => true,
        'automation' => true,
        'backend' => true,
        'cloud' => true,
        'data' => true,
        'devops' => true,
        'frontend' => true,
        'fullstack' => true,
        'mobile' => true,
        'testing' => true,
    ];

    private const WEAK_KEYWORDS = [
        'communication' => true,
        'design' => true,
        'product' => true,
    ];

    /**
     * @var array<string, list<string>>
     */
    private const CONTEXTUAL_SUPPRESSION = [
        'api' => ['rest_api', 'graphql', 'postman'],
        'automation' => ['test_automation', 'rpa', 'ci_cd', 'github_actions'],
        'backend' => ['laravel', 'django', 'nestjs', 'express', 'fastapi', 'spring', 'rails', 'dotnet', 'nodejs', 'php', 'python', 'java', 'go', 'ruby', 'csharp'],
        'cloud' => ['aws', 'azure', 'gcp'],
        'data' => ['data_engineering', 'machine_learning', 'bi', 'etl', 'airflow', 'spark', 'pandas', 'sql', 'mysql', 'postgresql', 'mongodb', 'redis', 'elasticsearch'],
        'devops' => ['docker', 'kubernetes', 'terraform', 'ci_cd', 'github_actions'],
        'frontend' => ['react', 'vue', 'angular', 'nextjs', 'nuxtjs', 'tailwind', 'typescript', 'javascript'],
        'mobile' => ['react_native', 'flutter', 'android', 'ios'],
        'testing' => ['qa', 'test_automation', 'unit_testing', 'integration_testing', 'e2e_testing', 'playwright', 'cypress', 'jest', 'phpunit', 'pytest', 'robot_framework', 'selenium', 'postman', 'owasp'],
    ];

    /**
     * @param list<string> $keywords
     * @return array{
     *     canonical_keywords: list<string>,
     *     explainable_keywords: list<string>,
     *     strong_keywords: list<string>,
     *     contextual_keywords: list<string>,
     *     weak_keywords: list<string>,
     *     has_strong_technical_signals: bool,
     *     quality: string
     * }
     */
    public function summarize(array $keywords): array
    {
        $canonicalKeywords = $this->canonicalKeywords($keywords);
        $strongKeywords = [];
        $contextualKeywords = [];
        $weakKeywords = [];

        foreach ($canonicalKeywords as $keyword) {
            $strength = $this->strength($keyword);

            if ($strength === 'strong') {
                $strongKeywords[] = $keyword;

                continue;
            }

            if ($strength === 'contextual') {
                $contextualKeywords[] = $keyword;

                continue;
            }

            $weakKeywords[] = $keyword;
        }

        $hasStrongTechnicalSignals = $strongKeywords !== [];
        $explainableKeywords = $strongKeywords;

        if ($hasStrongTechnicalSignals) {
            foreach ($contextualKeywords as $keyword) {
                if ($this->isSuppressedContextualKeyword($keyword, $canonicalKeywords)) {
                    continue;
                }

                $explainableKeywords[] = $keyword;
            }
        }

        return [
            'canonical_keywords' => $canonicalKeywords,
            'explainable_keywords' => array_values(array_unique($explainableKeywords)),
            'strong_keywords' => $strongKeywords,
            'contextual_keywords' => $contextualKeywords,
            'weak_keywords' => $weakKeywords,
            'has_strong_technical_signals' => $hasStrongTechnicalSignals,
            'quality' => $canonicalKeywords === []
                ? self::QUALITY_MISSING
                : ($hasStrongTechnicalSignals ? self::QUALITY_STRONG : self::QUALITY_LIMITED),
        ];
    }

    public function strength(string $keyword): string
    {
        $canonicalKeyword = $this->normalizeKeyword($keyword);

        if ($canonicalKeyword === '') {
            return 'weak';
        }

        if (isset(self::WEAK_KEYWORDS[$canonicalKeyword])) {
            return 'weak';
        }

        if (isset(self::CONTEXTUAL_KEYWORDS[$canonicalKeyword])) {
            return 'contextual';
        }

        return 'strong';
    }

    /**
     * @param list<string> $keywords
     * @return list<string>
     */
    private function canonicalKeywords(array $keywords): array
    {
        $canonicalKeywords = [];

        foreach ($keywords as $keyword) {
            $normalizedKeyword = $this->normalizeKeyword($keyword);

            if ($normalizedKeyword === '') {
                continue;
            }

            $canonicalKeywords[] = $normalizedKeyword;
        }

        return array_values(array_unique($canonicalKeywords));
    }

    /**
     * @param list<string> $keywords
     */
    private function isSuppressedContextualKeyword(string $keyword, array $keywords): bool
    {
        foreach (self::CONTEXTUAL_SUPPRESSION[$keyword] ?? [] as $candidate) {
            if (in_array($candidate, $keywords, true)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeKeyword(string $keyword): string
    {
        $canonicalKeyword = TechnicalKeywordTaxonomy::canonicalForExplicitSkill($keyword);

        if ($canonicalKeyword !== null) {
            return $canonicalKeyword;
        }

        $normalizedKeyword = strtolower(trim($keyword));
        $normalizedKeyword = preg_replace('/[^a-z0-9]+/', '_', $normalizedKeyword) ?? '';

        return trim($normalizedKeyword, '_');
    }
}
