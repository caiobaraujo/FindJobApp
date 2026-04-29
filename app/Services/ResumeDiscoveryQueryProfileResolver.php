<?php

namespace App\Services;

use Illuminate\Support\Str;
use Normalizer;

class ResumeDiscoveryQueryProfileResolver
{
    public function __construct(
        private readonly ResumeDiscoverySignalBuilder $resumeDiscoverySignalBuilder,
    ) {
    }

    /**
     * @param list<string>|null $coreSkills
     * @return list<array{key: string, label: string, signals: list<string>, aliases: list<string>, query: string}>
     */
    public function resolve(?string $searchQuery, ?string $resumeText, ?array $coreSkills): array
    {
        $normalizedSearchQuery = $this->normalizeText($searchQuery);

        if ($normalizedSearchQuery === null) {
            return [];
        }

        $searchTokens = $this->tokens($normalizedSearchQuery);

        if ($searchTokens === []) {
            return [];
        }

        $profiles = $this->resumeDiscoverySignalBuilder
            ->build($resumeText, $coreSkills)['query_profiles'];

        $applicableProfiles = [];

        foreach ($profiles as $profile) {
            if (! is_array($profile)) {
                continue;
            }

            if (! $this->profileApplies($searchTokens, $profile)) {
                continue;
            }

            $applicableProfiles[] = $profile;
        }

        return $applicableProfiles;
    }

    /**
     * @param list<string> $searchTokens
     * @param array{key: string, label: string, signals: list<string>, aliases: list<string>, query: string} $profile
     */
    private function profileApplies(array $searchTokens, array $profile): bool
    {
        foreach ([...$profile['signals'], ...$profile['aliases']] as $term) {
            $normalizedTerm = $this->normalizeText($term);

            if ($normalizedTerm === null) {
                continue;
            }

            if (array_intersect($searchTokens, $this->tokens($normalizedTerm)) !== []) {
                return true;
            }
        }

        return false;
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = $this->normalizeUnicode($value);
        $normalized = Str::of($normalized)
            ->lower()
            ->replace('vue.js', 'vuejs')
            ->replace('node.js', 'nodejs')
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->value();

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

        return array_values(array_unique(array_filter(
            array_map(fn (string $token): string => trim($token), $tokens),
            fn (string $token): bool => $token !== '',
        )));
    }
}
