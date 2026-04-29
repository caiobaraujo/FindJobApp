<?php

namespace App\Services;

use Normalizer;

class JobLeadKeywordExtractor
{
    private const MAX_KEYWORDS = 12;

    /**
     * @var array<string, true>
     */
    private const NOISE_TOKENS = [
        '@context' => true,
        '@type' => true,
        'analytics' => true,
        'breadcrumb' => true,
        'contenturl' => true,
        'datalayer' => true,
        'document' => true,
        'function' => true,
        'googletagmanager' => true,
        'gtag' => true,
        'itemlist' => true,
        'itemprop' => true,
        'itemtype' => true,
        'onclick' => true,
        'primaryimage' => true,
        'return' => true,
        'sameas' => true,
        'schema' => true,
        'schema.org' => true,
        'srcset' => true,
        'thumbnailurl' => true,
        'window' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const STOPWORDS = [
        'a' => true,
        'about' => true,
        'after' => true,
        'alem' => true,
        'all' => true,
        'also' => true,
        'an' => true,
        'and' => true,
        'ao' => true,
        'aos' => true,
        'apos' => true,
        'are' => true,
        'as' => true,
        'at' => true,
        'ate' => true,
        'auxiliar' => true,
        'be' => true,
        'bem' => true,
        'but' => true,
        'by' => true,
        'cada' => true,
        'caso' => true,
        'com' => true,
        'como' => true,
        'contra' => true,
        'da' => true,
        'das' => true,
        'de' => true,
        'delas' => true,
        'dele' => true,
        'deles' => true,
        'depois' => true,
        'desde' => true,
        'diversas' => true,
        'diversos' => true,
        'do' => true,
        'dos' => true,
        'during' => true,
        'e' => true,
        'each' => true,
        'ela' => true,
        'elas' => true,
        'ele' => true,
        'eles' => true,
        'em' => true,
        'entre' => true,
        'era' => true,
        'essa' => true,
        'essas' => true,
        'esse' => true,
        'esses' => true,
        'esta' => true,
        'estas' => true,
        'este' => true,
        'estes' => true,
        'for' => true,
        'from' => true,
        'foi' => true,
        'ha' => true,
        'if' => true,
        'in' => true,
        'into' => true,
        'is' => true,
        'isso' => true,
        'isto' => true,
        'it' => true,
        'just' => true,
        'like' => true,
        'mais' => true,
        'mas' => true,
        'mesma' => true,
        'mesmas' => true,
        'mesmo' => true,
        'mesmos' => true,
        'muita' => true,
        'muitas' => true,
        'muito' => true,
        'muitos' => true,
        'na' => true,
        'nas' => true,
        'no' => true,
        'nos' => true,
        'nossa' => true,
        'nossas' => true,
        'nosso' => true,
        'nossos' => true,
        'of' => true,
        'on' => true,
        'or' => true,
        'os' => true,
        'ou' => true,
        'our' => true,
        'para' => true,
        'pela' => true,
        'pelas' => true,
        'pelo' => true,
        'pelos' => true,
        'por' => true,
        'porque' => true,
        'que' => true,
        'quem' => true,
        'own' => true,
        'per' => true,
        'role' => true,
        'se' => true,
        'sem' => true,
        'ser' => true,
        'sera' => true,
        'seu' => true,
        'seus' => true,
        'sua' => true,
        'suas' => true,
        'some' => true,
        'sobre' => true,
        'strong' => true,
        'team' => true,
        'teams' => true,
        'tem' => true,
        'ter' => true,
        'that' => true,
        'the' => true,
        'their' => true,
        'this' => true,
        'through' => true,
        'time' => true,
        'to' => true,
        'um' => true,
        'uma' => true,
        'umas' => true,
        'uns' => true,
        'using' => true,
        'vai' => true,
        'varias' => true,
        'varios' => true,
        'we' => true,
        'week' => true,
        'weeks' => true,
        'will' => true,
        'work' => true,
        'worked' => true,
        'working' => true,
        'with' => true,
        'year' => true,
        'years' => true,
        'you' => true,
        'your' => true,
    ];

    public function __construct(
        private readonly TechnicalKeywordSignalQuality $technicalKeywordSignalQuality,
    ) {
    }

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

        $allKeywords = $this->extractAllKeywords($normalizedDescriptionText);
        $keywords = array_slice($allKeywords, 0, self::MAX_KEYWORDS);
        $quality = $this->technicalKeywordSignalQuality->summarize($allKeywords);

        return [
            'extracted_keywords' => $keywords,
            'ats_hints' => $this->buildAtsHints($normalizedDescriptionText, $keywords, $quality),
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

        return array_slice($this->taxonomyKeywords($normalizedDescriptionText), 0, self::MAX_KEYWORDS);
    }

    /**
     * @return list<string>
     */
    public function extractAllKeywords(?string $descriptionText): array
    {
        $normalizedDescriptionText = $this->normalizeDescriptionText($descriptionText);

        if ($normalizedDescriptionText === null) {
            return [];
        }

        return $this->taxonomyKeywords($normalizedDescriptionText);
    }

    private function normalizeDescriptionText(?string $descriptionText): ?string
    {
        if ($descriptionText === null) {
            return null;
        }

        $normalizedDescriptionText = $this->normalizeUnicode($descriptionText);
        $normalizedDescriptionText = html_entity_decode(strip_tags($normalizedDescriptionText), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalizedDescriptionText = strtolower($normalizedDescriptionText);
        $normalizedDescriptionText = preg_replace('/https?:\/\/\S+|www\.\S+/u', ' ', $normalizedDescriptionText) ?? $normalizedDescriptionText;
        $normalizedDescriptionText = preg_replace('/\b\S+\.(?:png|jpg|jpeg|gif|svg|webp|ico)\b/u', ' ', $normalizedDescriptionText) ?? $normalizedDescriptionText;
        $normalizedDescriptionText = preg_replace('/\b[a-z0-9_-]{25,}\b/u', ' ', $normalizedDescriptionText) ?? $normalizedDescriptionText;
        $normalizedDescriptionText = str_replace(['=>', '::', '{}', '[]', '();'], ' ', $normalizedDescriptionText);
        $normalizedDescriptionText = preg_replace('/[^a-z0-9\s\.\#\/\-\+\(\)]+/u', ' ', $normalizedDescriptionText) ?? $normalizedDescriptionText;
        $normalizedDescriptionText = trim(preg_replace('/\s+/', ' ', $normalizedDescriptionText) ?? '');

        if ($normalizedDescriptionText === '') {
            return null;
        }

        return $this->removeNoiseTokens($normalizedDescriptionText);
    }

    /**
     * @return list<string>
     */
    private function taxonomyKeywords(string $descriptionText): array
    {
        $matches = [];

        foreach (TechnicalKeywordTaxonomy::definitions() as $canonical => $definition) {
            $firstPosition = $this->firstMatchPosition($descriptionText, $canonical, $definition['aliases'], $definition['requires_context'] ?? []);

            if ($firstPosition === null) {
                continue;
            }

            $matches[] = [
                'canonical' => $canonical,
                'position' => $firstPosition,
                'specificity' => $this->specificity($definition['aliases']),
            ];
        }

        usort($matches, function (array $left, array $right): int {
            $positionComparison = $left['position'] <=> $right['position'];

            if ($positionComparison !== 0) {
                return $positionComparison;
            }

            $specificityComparison = $right['specificity'] <=> $left['specificity'];

            if ($specificityComparison !== 0) {
                return $specificityComparison;
            }

            return strcmp($left['canonical'], $right['canonical']);
        });

        return array_values(array_unique(array_column($matches, 'canonical')));
    }

    private function removeNoiseTokens(string $descriptionText): ?string
    {
        $tokens = preg_split('/\s+/', $descriptionText) ?: [];
        $cleanTokens = array_values(array_filter($tokens, function (string $token): bool {
            if (isset(self::STOPWORDS[$token])) {
                return false;
            }

            if (isset(self::NOISE_TOKENS[$token])) {
                return false;
            }

            return ! preg_match('/^[\.\#\/\-\+\(\)]+$/', $token);
        }));

        if ($cleanTokens === []) {
            return null;
        }

        return implode(' ', $cleanTokens);
    }

    /**
     * @param list<string> $aliases
     * @param list<string> $requiredContext
     */
    private function firstMatchPosition(string $descriptionText, string $canonical, array $aliases, array $requiredContext): ?int
    {
        $firstPosition = null;

        foreach ($aliases as $alias) {
            $pattern = $this->aliasPattern($alias);

            if (preg_match($pattern, $descriptionText, $matches, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }

            $matchedText = (string) $matches[0][0];
            $position = (int) $matches[0][1];

            if (! $this->hasRequiredContext($descriptionText, $position, strlen($matchedText), $requiredContext, $canonical, $alias, $matchedText)) {
                continue;
            }

            if ($canonical === 'go' && $alias === 'go' && ! $this->hasStandaloneGoContext($descriptionText, $position, strlen($matchedText))) {
                continue;
            }

            if ($firstPosition === null || $position < $firstPosition) {
                $firstPosition = $position;
            }
        }

        return $firstPosition;
    }

    private function aliasPattern(string $alias): string
    {
        $quotedAlias = preg_quote(strtolower($alias), '/');
        $quotedAlias = str_replace('\ ', '\s+', $quotedAlias);

        return '/(?<![a-z0-9])'.$quotedAlias.'(?![a-z0-9])/u';
    }

    /**
     * @param list<string> $requiredContext
     */
    private function hasRequiredContext(string $descriptionText, int $position, int $matchLength, array $requiredContext, string $canonical, string $alias, string $matchedText): bool
    {
        if ($requiredContext === []) {
            return true;
        }

        if ($canonical === 'rest_api' && $alias !== 'rest') {
            return true;
        }

        $contextWindow = substr($descriptionText, max(0, $position - 100), $matchLength + 200) ?: $descriptionText;

        foreach ($requiredContext as $contextTerm) {
            $quotedContextTerm = preg_quote(strtolower($contextTerm), '/');
            $quotedContextTerm = str_replace('\ ', '\s+', $quotedContextTerm);

            if (preg_match('/(?<![a-z0-9])'.$quotedContextTerm.'(?![a-z0-9])/u', strtolower($matchedText)) === 1) {
                return true;
            }

            if (preg_match('/(?<![a-z0-9])'.$quotedContextTerm.'(?![a-z0-9])/u', $contextWindow) === 1) {
                return true;
            }
        }

        return false;
    }

    private function hasStandaloneGoContext(string $descriptionText, int $position, int $matchLength): bool
    {
        $contextWindow = substr($descriptionText, max(0, $position - 30), $matchLength + 60) ?: $descriptionText;

        return preg_match(
            '/(?<![a-z0-9])(backend|developer|engineer|software|microservices|grpc|kubernetes|service|platform)(?![a-z0-9]).{0,30}(?<![a-z0-9])go(?![a-z0-9])|(?<![a-z0-9])go(?![a-z0-9]).{0,30}(?<![a-z0-9])(backend|developer|engineer|software|microservices|grpc|kubernetes|service|platform)(?![a-z0-9])/u',
            $contextWindow,
        ) === 1;
    }

    /**
     * @param list<string> $aliases
     */
    private function specificity(array $aliases): int
    {
        return collect($aliases)
            ->map(fn (string $alias): int => strlen($alias))
            ->max() ?? 0;
    }

    /**
     * @param list<string> $keywords
     * @param array{
     *     explainable_keywords: list<string>,
     *     has_strong_technical_signals: bool,
     *     quality: string
     * } $quality
     * @return list<string>
     */
    private function buildAtsHints(string $descriptionText, array $keywords, array $quality): array
    {
        $hints = [];

        if ($quality['quality'] === TechnicalKeywordSignalQuality::QUALITY_LIMITED) {
            $hints[] = 'Only broad technical context was found. Add the full job posting to surface stronger stack-specific signals.';
        }

        if (count($keywords) < 4) {
            $hints[] = 'Paste more of the job description to surface a stronger ATS keyword set.';
        }

        if (strlen($descriptionText) < 400) {
            $hints[] = 'The description looks short. Add the full posting before tailoring your resume.';
        }

        if ($quality['explainable_keywords'] !== []) {
            $hints[] = 'Likely ATS terms to reflect in your resume: '.implode(', ', array_slice($quality['explainable_keywords'], 0, 6)).'.';
        }

        if ($hints === []) {
            $hints[] = 'Review the extracted keywords and mirror the strongest matching terms in your resume bullets.';
        }

        return $hints;
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
}
