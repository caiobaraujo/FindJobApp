<?php

namespace App\Services;

use Illuminate\Support\Str;
use Normalizer;

class JobSearchIntentParser
{
    /**
     * @var array<string, string>
     */
    private const ALIASES = [
        'bh' => 'belo horizonte',
        'brasil' => 'brazil',
        'hibrida' => 'hybrid',
        'hibrido' => 'hybrid',
        'js' => 'javascript',
        'node js' => 'node',
        'nodejs' => 'node',
        'onsite' => 'onsite',
        'ou' => 'or',
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
    private const LOCATION_TOKENS = [
        'brazil' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const WORK_MODE_TOKENS = [
        'hybrid' => true,
        'onsite' => true,
        'remote' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const OPERATOR_TOKENS = [
        'or' => true,
    ];

    /**
     * @return array{
     *     raw_query: string|null,
     *     query: string|null,
     *     keywords: list<string>,
     *     operator: 'or'|'any',
     *     location: string|null,
     *     work_mode: string|null
     * }
     */
    public function parse(?string $query): array
    {
        $rawQuery = is_string($query) ? trim($query) : '';
        $normalizedQuery = $this->normalizeText($rawQuery);

        if ($normalizedQuery === null) {
            return [
                'raw_query' => $rawQuery !== '' ? $rawQuery : null,
                'query' => null,
                'keywords' => [],
                'operator' => 'any',
                'location' => null,
                'work_mode' => null,
            ];
        }

        $tokens = $this->tokens($normalizedQuery);
        $operator = in_array('or', $tokens, true) ? 'or' : 'any';
        $location = null;
        $workMode = null;
        $keywords = [];

        foreach ($tokens as $token) {
            if (isset(self::OPERATOR_TOKENS[$token])) {
                continue;
            }

            if (isset(self::LOCATION_TOKENS[$token])) {
                $location = $token;

                continue;
            }

            if (isset(self::WORK_MODE_TOKENS[$token])) {
                $workMode = $token;

                continue;
            }

            $keywords[] = $token;
        }

        $canonicalQueryTokens = [
            ...$keywords,
            ...($workMode !== null ? [$workMode] : []),
            ...($location !== null ? [$location] : []),
        ];

        return [
            'raw_query' => $rawQuery !== '' ? $rawQuery : null,
            'query' => $canonicalQueryTokens === [] ? null : implode(' ', $canonicalQueryTokens),
            'keywords' => array_values(array_unique($keywords)),
            'operator' => $operator,
            'location' => $location,
            'work_mode' => $workMode,
        ];
    }

    private function normalizeText(string $value): ?string
    {
        $normalized = $this->normalizeUnicode($value);

        $normalized = Str::of($normalized)
            ->lower()
            ->replace('vue.js', 'vuejs')
            ->replace('node.js', 'nodejs')
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->value();

        if ($normalized === '') {
            return null;
        }

        foreach (self::ALIASES as $alias => $canonical) {
            $normalized = preg_replace(
                '/\b'.preg_quote($alias, '/').'\b/',
                $canonical,
                $normalized,
            ) ?? $normalized;
        }

        $normalized = trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);

        return $normalized === '' ? null : $normalized;
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
    private function tokens(string $value): array
    {
        $tokens = preg_split('/\s+/', $value) ?: [];

        return array_values(array_filter(
            array_map(fn (string $token): string => trim($token), $tokens),
            fn (string $token): bool => $token !== '',
        ));
    }
}
