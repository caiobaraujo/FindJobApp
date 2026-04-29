<?php

namespace App\Services;

class TechnicalKeywordDisplay
{
    /**
     * @var array<string, string>
     */
    private const LABELS = [
        'ai_ml' => 'AI/ML',
        'api' => 'API',
        'aws' => 'AWS',
        'bi' => 'BI',
        'ci_cd' => 'CI/CD',
        'csharp' => 'C#',
        'data_engineering' => 'Data Engineering',
        'ddd' => 'DDD',
        'dotnet' => '.NET',
        'e2e_testing' => 'E2E Testing',
        'etl' => 'ETL',
        'fastapi' => 'FastAPI',
        'gcp' => 'GCP',
        'github_actions' => 'GitHub Actions',
        'graphql' => 'GraphQL',
        'ios' => 'iOS',
        'javascript' => 'JavaScript',
        'llm' => 'LLM',
        'machine_learning' => 'Machine Learning',
        'mysql' => 'MySQL',
        'nestjs' => 'NestJS',
        'nextjs' => 'Next.js',
        'nlp' => 'NLP',
        'nodejs' => 'Node.js',
        'nuxtjs' => 'Nuxt.js',
        'openai' => 'OpenAI',
        'php' => 'PHP',
        'phpunit' => 'PHPUnit',
        'postgresql' => 'PostgreSQL',
        'qa' => 'QA',
        'react_native' => 'React Native',
        'rest_api' => 'REST API',
        'sql' => 'SQL',
        'tailwind' => 'Tailwind CSS',
        'typescript' => 'TypeScript',
        'unit_testing' => 'Unit Testing',
    ];

    /**
     * @param list<string> $keywords
     * @return list<string>
     */
    public function displayKeywords(array $keywords): array
    {
        $canonicalKeywords = $this->canonicalKeywords($keywords);
        $visibleKeywords = [];

        foreach ($canonicalKeywords as $keyword) {
            if ($this->isRedundant($keyword, $canonicalKeywords)) {
                continue;
            }

            $visibleKeywords[] = $this->label($keyword);
        }

        return array_values(array_unique($visibleKeywords));
    }

    public function label(string $keyword): string
    {
        $normalizedKeyword = $this->normalizeKeyword($keyword);

        if ($normalizedKeyword === '') {
            return '';
        }

        return self::LABELS[$normalizedKeyword]
            ?? ucwords(str_replace('_', ' ', $normalizedKeyword));
    }

    /**
     * @param list<string> $keywords
     * @return list<string>
     */
    private function canonicalKeywords(array $keywords): array
    {
        $normalizedKeywords = [];

        foreach ($keywords as $keyword) {
            $normalizedKeyword = $this->normalizeKeyword($keyword);

            if ($normalizedKeyword === '') {
                continue;
            }

            $normalizedKeywords[] = $normalizedKeyword;
        }

        return array_values(array_unique($normalizedKeywords));
    }

    /**
     * @param list<string> $keywords
     */
    private function isRedundant(string $keyword, array $keywords): bool
    {
        $keywordTokens = $this->tokens($keyword);

        if (count($keywordTokens) === 0) {
            return false;
        }

        foreach ($keywords as $candidate) {
            if ($candidate === $keyword) {
                continue;
            }

            $candidateTokens = $this->tokens($candidate);

            if (count($candidateTokens) <= count($keywordTokens)) {
                continue;
            }

            if ($this->containsAllTokens($candidateTokens, $keywordTokens)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function tokens(string $keyword): array
    {
        $tokens = preg_split('/[_\s\/]+/', $keyword) ?: [];

        return array_values(array_filter($tokens, fn (string $token): bool => $token !== ''));
    }

    /**
     * @param list<string> $haystack
     * @param list<string> $needle
     */
    private function containsAllTokens(array $haystack, array $needle): bool
    {
        foreach ($needle as $token) {
            if (! in_array($token, $haystack, true)) {
                return false;
            }
        }

        return true;
    }

    private function normalizeKeyword(string $keyword): string
    {
        $normalizedKeyword = strtolower(trim($keyword));
        $normalizedKeyword = preg_replace('/[^a-z0-9]+/', '_', $normalizedKeyword) ?? '';

        return trim($normalizedKeyword, '_');
    }
}
