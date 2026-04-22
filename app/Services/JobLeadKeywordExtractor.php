<?php

namespace App\Services;

use Normalizer;

class JobLeadKeywordExtractor
{
    private const MAX_KEYWORDS = 12;

    /**
     * @var array<string, true>
     */
    private const ROLE_TERMS = [
        'analyst' => true,
        'arquiteto' => true,
        'designer' => true,
        'developer' => true,
        'desenvolvedor' => true,
        'engineer' => true,
        'engenheiro' => true,
        'manager' => true,
        'specialist' => true,
    ];

    /**
     * @var list<string>
     */
    private const IMPORTANT_TERMS = [
        'api',
        'apis',
        'aws',
        'backend',
        'cloud',
        'css',
        'data',
        'design',
        'devops',
        'docker',
        'frontend',
        'graphql',
        'java',
        'javascript',
        'kubernetes',
        'laravel',
        'mysql',
        'node',
        'php',
        'postgresql',
        'product',
        'python',
        'react',
        'resume',
        'sql',
        'tailwind',
        'testing',
        'typescript',
        'vue',
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
        'team' => true,
        'tem' => true,
        'ter' => true,
        'that' => true,
        'the' => true,
        'their' => true,
        'this' => true,
        'through' => true,
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

        if ($words === []) {
            return [];
        }

        $keywords = array_merge(
            $this->sortedCandidates($this->counts($words), false),
            $this->sortedCandidates($this->counts($this->phrases($words)), true),
        );

        return array_slice(array_values(array_unique($keywords)), 0, self::MAX_KEYWORDS);
    }

    private function normalizeDescriptionText(?string $descriptionText): ?string
    {
        if ($descriptionText === null) {
            return null;
        }

        $normalizedDescriptionText = $this->normalizeUnicode($descriptionText);
        $normalizedDescriptionText = strtolower($normalizedDescriptionText);
        $normalizedDescriptionText = preg_replace('/[^a-z0-9\s]+/', ' ', $normalizedDescriptionText) ?? '';
        $normalizedDescriptionText = trim(preg_replace('/\s+/', ' ', $normalizedDescriptionText) ?? '');

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
        $words = preg_split('/\s+/', $descriptionText) ?: [];

        return array_values(array_filter(
            $words,
            fn (string $word): bool => $this->isUsefulWord($word),
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
    private function sortedCandidates(array $counts, bool $phrases): array
    {
        $candidates = [];

        foreach ($counts as $keyword => $count) {
            if (! $this->shouldKeepCandidate($keyword, $count, $phrases)) {
                continue;
            }

            $candidates[$keyword] = $count;
        }

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
        $scoreComparison = $this->candidateScore($counts[$right], $right)
            <=> $this->candidateScore($counts[$left], $left);

        if ($scoreComparison !== 0) {
            return $scoreComparison;
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
            fn (string $term): bool => str_contains($descriptionText, $term),
        ));

        if ($importantTerms !== []) {
            $hints[] = 'Likely ATS terms to reflect in your resume: '.implode(', ', array_slice($importantTerms, 0, 6)).'.';
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

    private function isUsefulWord(string $word): bool
    {
        if (strlen($word) < 3) {
            return false;
        }

        if (isset(self::STOPWORDS[$word])) {
            return false;
        }

        return preg_match('/^[a-z0-9]+$/', $word) === 1;
    }

    private function shouldKeepCandidate(string $keyword, int $count, bool $phrases): bool
    {
        if ($phrases) {
            if ($count >= 2) {
                return true;
            }

            return $this->isMeaningfulPhrase($keyword);
        }

        if ($count >= 2) {
            return true;
        }

        return $this->isImportantTerm($keyword);
    }

    private function candidateScore(int $count, string $keyword): int
    {
        $score = $count * 10;

        if ($this->containsImportantTerm($keyword)) {
            $score += 100;
        }

        if (str_contains($keyword, ' ')) {
            $score += 20;
        }

        return $score;
    }

    private function isImportantTerm(string $keyword): bool
    {
        return in_array($keyword, self::IMPORTANT_TERMS, true);
    }

    private function containsImportantTerm(string $keyword): bool
    {
        foreach (self::IMPORTANT_TERMS as $importantTerm) {
            if (str_contains($keyword, $importantTerm)) {
                return true;
            }
        }

        return false;
    }

    private function isMeaningfulPhrase(string $keyword): bool
    {
        [$firstWord, $secondWord] = explode(' ', $keyword, 2);

        if ($this->isImportantTerm($firstWord) && $this->isImportantTerm($secondWord)) {
            return true;
        }

        if ($this->isImportantTerm($firstWord) && isset(self::ROLE_TERMS[$secondWord])) {
            return true;
        }

        if ($this->isImportantTerm($secondWord) && isset(self::ROLE_TERMS[$firstWord])) {
            return true;
        }

        return false;
    }
}
