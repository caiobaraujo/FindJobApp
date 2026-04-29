<?php

namespace App\Services;

use Illuminate\Support\Str;
use Normalizer;

class ResumeDiscoverySignalBuilder
{
    /**
     * @var list<string>
     */
    private const DISCOVERY_SKILL_ORDER = [
        'vue',
        'angular',
        'python',
        'django',
        'php',
        'laravel',
        'fullstack',
        'nodejs',
        'javascript',
        'sql',
        'mysql',
        'docker',
        'openai',
        'llm',
        'nlp',
        'chatbot',
        'frontend',
        'backend',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const ALIAS_OVERRIDES = [
        'nodejs' => ['node'],
    ];

    public function __construct(
        private readonly JobLeadKeywordExtractor $jobLeadKeywordExtractor,
    ) {
    }

    /**
     * @param list<string>|null $coreSkills
     * @return array{
     *     detected_skills: list<string>,
     *     role_families: list<string>,
     *     canonical_skills: list<string>,
     *     aliases: list<string>,
     *     query_profiles: list<array{key: string, label: string, signals: list<string>, aliases: list<string>, query: string}>
     * }
     */
    public function build(?string $resumeText, ?array $coreSkills): array
    {
        $canonicalSkills = $this->canonicalSkills($resumeText, $coreSkills);
        $roleFamilies = $this->roleFamilies($canonicalSkills);
        $aliases = $this->aliases($canonicalSkills);
        $queryProfiles = $this->queryProfiles($canonicalSkills);

        return [
            'detected_skills' => $this->detectedSkills($resumeText, $coreSkills),
            'role_families' => $roleFamilies,
            'canonical_skills' => $canonicalSkills,
            'aliases' => $aliases,
            'query_profiles' => $queryProfiles,
        ];
    }

    /**
     * @param list<string>|null $coreSkills
     * @return list<string>
     */
    public function matchSignals(?string $resumeText, ?array $coreSkills): array
    {
        $canonicalSkills = $this->canonicalSkills($resumeText, $coreSkills);
        $technicalKeywords = $this->technicalKeywords($resumeText, $coreSkills);

        return array_values(array_unique([
            ...$technicalKeywords,
            ...$this->roleFamilies($canonicalSkills),
        ]));
    }

    /**
     * @param list<string>|null $coreSkills
     * @return list<string>
     */
    public function detectedSkills(?string $resumeText, ?array $coreSkills, int $limit = 10): array
    {
        return array_slice($this->technicalKeywords($resumeText, $coreSkills), 0, $limit);
    }

    /**
     * @param list<string>|null $coreSkills
     * @return list<string>
     */
    private function canonicalSkills(?string $resumeText, ?array $coreSkills): array
    {
        $technicalKeywords = $this->technicalKeywords($resumeText, $coreSkills);
        $canonicalSkills = [];

        foreach (self::DISCOVERY_SKILL_ORDER as $canonicalSkill) {
            if (! in_array($canonicalSkill, $technicalKeywords, true)) {
                continue;
            }

            $canonicalSkills[] = $canonicalSkill;
        }

        return $canonicalSkills;
    }

    /**
     * @param list<string> $canonicalSkills
     * @return list<string>
     */
    private function roleFamilies(array $canonicalSkills): array
    {
        $families = [];

        if (in_array('vue', $canonicalSkills, true)) {
            $families = [...$families, 'frontend_vue', 'frontend', 'javascript'];
        }

        if (in_array('angular', $canonicalSkills, true)) {
            $families = [...$families, 'frontend_angular', 'frontend', 'javascript'];
        }

        if (in_array('python', $canonicalSkills, true) || in_array('django', $canonicalSkills, true)) {
            $families = [...$families, 'backend_python', 'backend'];
        }

        if (in_array('php', $canonicalSkills, true) || in_array('laravel', $canonicalSkills, true)) {
            $families = [...$families, 'backend_php', 'backend'];
        }

        if (in_array('fullstack', $canonicalSkills, true)) {
            $families = [...$families, 'fullstack', 'backend', 'frontend'];
        }

        if (in_array('nodejs', $canonicalSkills, true)) {
            $families = [...$families, 'backend', 'javascript'];
        }

        if (in_array('sql', $canonicalSkills, true) || in_array('mysql', $canonicalSkills, true)) {
            $families[] = 'database';
        }

        if (
            in_array('openai', $canonicalSkills, true)
            || in_array('llm', $canonicalSkills, true)
            || in_array('nlp', $canonicalSkills, true)
            || in_array('chatbot', $canonicalSkills, true)
        ) {
            $families[] = 'ai_applied';
        }

        if (in_array('frontend', $canonicalSkills, true)) {
            $families[] = 'frontend';
        }

        if (in_array('backend', $canonicalSkills, true)) {
            $families[] = 'backend';
        }

        return array_values(array_unique($families));
    }

    /**
     * @param list<string> $canonicalSkills
     * @return list<string>
     */
    private function aliases(array $canonicalSkills): array
    {
        $aliases = [];
        $definitions = TechnicalKeywordTaxonomy::definitions();

        foreach ($canonicalSkills as $canonicalSkill) {
            foreach ($definitions[$canonicalSkill]['aliases'] ?? [] as $alias) {
                $aliases[] = $alias;
            }

            foreach (self::ALIAS_OVERRIDES[$canonicalSkill] ?? [] as $alias) {
                $aliases[] = $alias;
            }
        }

        return array_values(array_unique($aliases));
    }

    /**
     * @param list<string> $canonicalSkills
     * @return list<array{key: string, label: string, signals: list<string>, aliases: list<string>, query: string}>
     */
    private function queryProfiles(array $canonicalSkills): array
    {
        $profiles = [];

        if (in_array('vue', $canonicalSkills, true)) {
            $profiles[] = $this->queryProfile(
                'frontend_vue',
                'Vue frontend',
                ['frontend_vue', 'frontend', 'javascript', 'vue'],
                ['vue', 'vue js', 'vuejs'],
            );
        }

        if (in_array('angular', $canonicalSkills, true)) {
            $profiles[] = $this->queryProfile(
                'frontend_angular',
                'Angular frontend',
                ['frontend_angular', 'frontend', 'javascript', 'angular'],
                ['angular', 'angular js', 'angularjs'],
            );
        }

        if (in_array('python', $canonicalSkills, true) || in_array('django', $canonicalSkills, true)) {
            $profiles[] = $this->queryProfile(
                'backend_python',
                'Python backend',
                array_values(array_filter(['backend_python', 'backend', in_array('python', $canonicalSkills, true) ? 'python' : null, in_array('django', $canonicalSkills, true) ? 'django' : null])),
                $this->profileAliases(['python', 'django'], $canonicalSkills),
            );
        }

        if (in_array('php', $canonicalSkills, true) || in_array('laravel', $canonicalSkills, true)) {
            $profiles[] = $this->queryProfile(
                'backend_php',
                'PHP backend',
                array_values(array_filter(['backend_php', 'backend', in_array('php', $canonicalSkills, true) ? 'php' : null, in_array('laravel', $canonicalSkills, true) ? 'laravel' : null])),
                $this->profileAliases(['php', 'laravel'], $canonicalSkills),
            );
        }

        if (in_array('fullstack', $canonicalSkills, true)) {
            $profiles[] = $this->queryProfile(
                'fullstack',
                'Full stack',
                ['fullstack', 'backend', 'frontend'],
                ['full stack', 'fullstack'],
            );
        }

        if (in_array('nodejs', $canonicalSkills, true)) {
            $profiles[] = $this->queryProfile(
                'nodejs',
                'Node.js backend',
                ['nodejs', 'javascript', 'backend'],
                ['node', 'node js', 'nodejs'],
            );
        }

        if (in_array('sql', $canonicalSkills, true) || in_array('mysql', $canonicalSkills, true)) {
            $profiles[] = $this->queryProfile(
                'database',
                'Database',
                array_values(array_filter(['database', in_array('sql', $canonicalSkills, true) ? 'sql' : null, in_array('mysql', $canonicalSkills, true) ? 'mysql' : null])),
                $this->profileAliases(['sql', 'mysql'], $canonicalSkills),
            );
        }

        if (
            in_array('openai', $canonicalSkills, true)
            || in_array('llm', $canonicalSkills, true)
            || in_array('nlp', $canonicalSkills, true)
            || in_array('chatbot', $canonicalSkills, true)
        ) {
            $profiles[] = $this->queryProfile(
                'ai_applied',
                'Applied AI',
                array_values(array_filter([
                    'ai_applied',
                    in_array('chatbot', $canonicalSkills, true) ? 'chatbot' : null,
                    in_array('nlp', $canonicalSkills, true) ? 'nlp' : null,
                    in_array('llm', $canonicalSkills, true) ? 'llm' : null,
                    in_array('openai', $canonicalSkills, true) ? 'openai' : null,
                ])),
                $this->profileAliases(['chatbot', 'nlp', 'llm', 'openai'], $canonicalSkills),
            );
        }

        return $profiles;
    }

    /**
     * @param list<string> $signals
     * @param list<string> $aliases
     * @return array{key: string, label: string, signals: list<string>, aliases: list<string>, query: string}
     */
    private function queryProfile(string $key, string $label, array $signals, array $aliases): array
    {
        $uniqueSignals = array_values(array_unique($signals));
        $uniqueAliases = array_values(array_unique($aliases));

        return [
            'key' => $key,
            'label' => $label,
            'signals' => $uniqueSignals,
            'aliases' => $uniqueAliases,
            'query' => implode(' ', $uniqueSignals),
        ];
    }

    /**
     * @param list<string> $profileCanonicalSkills
     * @param list<string> $canonicalSkills
     * @return list<string>
     */
    private function profileAliases(array $profileCanonicalSkills, array $canonicalSkills): array
    {
        $aliases = [];
        $availableAliases = collect($this->aliases($canonicalSkills));

        foreach ($profileCanonicalSkills as $canonicalSkill) {
            if (! in_array($canonicalSkill, $canonicalSkills, true)) {
                continue;
            }

            $skillAliases = $this->aliases([$canonicalSkill]);
            $aliases = [...$aliases, ...$availableAliases->intersect($skillAliases)->values()->all()];
        }

        return array_values(array_unique($aliases));
    }

    /**
     * @param list<string>|null $coreSkills
     * @return list<string>
     */
    private function technicalKeywords(?string $resumeText, ?array $coreSkills): array
    {
        $keywords = [];

        foreach ($coreSkills ?? [] as $skill) {
            if (! is_string($skill)) {
                continue;
            }

            $canonicalSkill = TechnicalKeywordTaxonomy::canonicalForExplicitSkill($skill);

            if ($canonicalSkill !== null) {
                $keywords[] = $canonicalSkill;
                continue;
            }

            $keywords = [...$keywords, ...$this->jobLeadKeywordExtractor->extractKeywords($skill)];
        }

        if (filled($resumeText)) {
            foreach ($this->jobLeadKeywordExtractor->extractAllKeywords($resumeText) as $keyword) {
                $keywords[] = $keyword;
            }
        }

        return array_values(array_unique($keywords));
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = $this->normalizeUnicode($value);
        $normalized = Str::of($normalized)
            ->lower()
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
}
