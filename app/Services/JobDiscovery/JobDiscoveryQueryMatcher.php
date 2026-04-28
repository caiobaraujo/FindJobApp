<?php

namespace App\Services\JobDiscovery;

use App\Services\JobLeadKeywordExtractor;
use Normalizer;

class JobDiscoveryQueryMatcher
{
    /**
     * @var array<string, string>
     */
    private const ALIASES = [
        'bh' => 'belo horizonte',
        'hibrida' => 'hybrid',
        'hibrido' => 'hybrid',
        'hybrid' => 'hybrid',
        'node js' => 'node',
        'nodejs' => 'node',
        'onsite' => 'onsite',
        'presencial' => 'onsite',
        'remote' => 'remote',
        'remota' => 'remote',
        'remoto' => 'remote',
        'vue js' => 'vue',
        'vuejs' => 'vue',
    ];

    /**
     * @var array<string, true>
     */
    private const LOCATION_PHRASES = [
        'belo horizonte' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const STOPWORDS = [
        'a' => true,
        'and' => true,
        'de' => true,
        'del' => true,
        'e' => true,
        'em' => true,
        'en' => true,
        'for' => true,
        'job' => true,
        'jobs' => true,
        'la' => true,
        'o' => true,
        'or' => true,
        'para' => true,
        'role' => true,
        'the' => true,
        'vaga' => true,
        'vagas' => true,
        'y' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const WORK_MODE_TOKENS = [
        'hybrid' => true,
        'onsite' => true,
        'remote' => true,
    ];

    public function __construct(
        private readonly JobLeadKeywordExtractor $jobLeadKeywordExtractor,
    ) {
    }

    /**
     * @param array{
     *     company_name?: string|null,
     *     description_text?: string|null,
     *     extracted_keywords?: array<int, string>|null,
     *     job_title?: string|null,
     *     location?: string|null,
     *     work_mode?: string|null
     * } $job
     */
    public function matches(?string $query, array $job): bool
    {
        $normalizedQuery = $this->normalizeText($query);

        if ($normalizedQuery === null) {
            return true;
        }

        $queryTokens = $this->tokens($normalizedQuery);

        if ($queryTokens === []) {
            return true;
        }

        $locationPhrases = array_values(array_filter(
            $queryTokens,
            fn (string $token): bool => isset(self::LOCATION_PHRASES[$token]),
        ));
        $workModeTokens = array_values(array_filter(
            $queryTokens,
            fn (string $token): bool => isset(self::WORK_MODE_TOKENS[$token]),
        ));
        $generalTokens = array_values(array_filter(
            $queryTokens,
            fn (string $token): bool => ! isset(self::LOCATION_PHRASES[$token])
                && ! isset(self::WORK_MODE_TOKENS[$token]),
        ));

        $haystackText = $this->jobHaystackText($job);
        $haystackTokens = $this->tokens($haystackText);

        if ($generalTokens !== [] && ! $this->containsAnyToken($generalTokens, $haystackTokens, $haystackText)) {
            return false;
        }

        if ($workModeTokens !== [] && ! $this->containsAllTokens($workModeTokens, $haystackTokens, $haystackText)) {
            return false;
        }

        if ($locationPhrases !== [] && ! $this->containsAllPhrases($locationPhrases, $haystackText)) {
            return false;
        }

        if ($generalTokens !== []) {
            return true;
        }

        return $workModeTokens !== [] || $locationPhrases !== [];
    }

    private function jobHaystackText(array $job): string
    {
        $keywords = $job['extracted_keywords'] ?? null;

        if (! is_array($keywords) || $keywords === []) {
            $keywords = $this->jobLeadKeywordExtractor->extractKeywords($job['description_text'] ?? null);
        }

        $text = implode(' ', array_filter([
            $job['job_title'] ?? null,
            $job['company_name'] ?? null,
            $job['location'] ?? null,
            $job['work_mode'] ?? null,
            $job['description_text'] ?? null,
            implode(' ', array_filter($keywords, fn (mixed $keyword): bool => is_string($keyword))),
        ], fn (mixed $value): bool => is_string($value) && trim($value) !== ''));

        return $this->normalizeText($text) ?? '';
    }

    /**
     * @param list<string> $tokens
     * @param list<string> $haystackTokens
     */
    private function containsAllTokens(array $tokens, array $haystackTokens, string $haystackText): bool
    {
        foreach (array_values(array_unique($tokens)) as $token) {
            if (! $this->containsToken($token, $haystackTokens, $haystackText)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $phrases
     */
    private function containsAllPhrases(array $phrases, string $haystackText): bool
    {
        foreach (array_values(array_unique($phrases)) as $phrase) {
            if (! str_contains($haystackText, $phrase)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $tokens
     * @param list<string> $haystackTokens
     */
    private function containsAnyToken(array $tokens, array $haystackTokens, string $haystackText): bool
    {
        foreach (array_values(array_unique($tokens)) as $token) {
            if ($this->containsToken($token, $haystackTokens, $haystackText)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $haystackTokens
     */
    private function containsToken(string $token, array $haystackTokens, string $haystackText): bool
    {
        if (in_array($token, $haystackTokens, true)) {
            return true;
        }

        if (str_contains($token, ' ')) {
            return str_contains($haystackText, $token);
        }

        return preg_match('/\b'.preg_quote($token, '/').'\b/', $haystackText) === 1;
    }

    private function normalizeText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $normalizedText = trim($text);

        if ($normalizedText === '') {
            return null;
        }

        $normalizedText = $this->normalizeUnicode($normalizedText);
        $normalizedText = strtolower($normalizedText);
        $normalizedText = str_replace(['vue.js', 'node.js'], ['vuejs', 'nodejs'], $normalizedText);

        foreach (self::ALIASES as $search => $replacement) {
            $pattern = '/\b'.preg_quote($search, '/').'\b/u';
            $normalizedText = preg_replace($pattern, $replacement, $normalizedText) ?? $normalizedText;
        }

        $normalizedText = preg_replace('/[^a-z0-9\s]+/', ' ', $normalizedText) ?? $normalizedText;
        $normalizedText = trim(preg_replace('/\s+/', ' ', $normalizedText) ?? $normalizedText);

        if ($normalizedText === '') {
            return null;
        }

        return $normalizedText;
    }

    private function normalizeUnicode(string $text): string
    {
        if (class_exists(Normalizer::class)) {
            $normalizedText = Normalizer::normalize($text, Normalizer::FORM_KD);

            if (is_string($normalizedText)) {
                return preg_replace('/\p{Mn}+/u', '', $normalizedText) ?? $normalizedText;
            }
        }

        $transliteratedText = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        if ($transliteratedText === false) {
            return $text;
        }

        return $transliteratedText;
    }

    /**
     * @return list<string>
     */
    private function tokens(string $text): array
    {
        $words = preg_split('/\s+/', $text) ?: [];
        $tokens = [];

        for ($index = 0; $index < count($words); $index++) {
            $word = trim($words[$index]);

            if ($word === '' || isset(self::STOPWORDS[$word])) {
                continue;
            }

            if ($word === 'belo' && ($words[$index + 1] ?? null) === 'horizonte') {
                $tokens[] = 'belo horizonte';
                $index++;

                continue;
            }

            $tokens[] = $word;
        }

        return array_values(array_unique($tokens));
    }
}
