<?php

namespace App\Services;

use Illuminate\Support\Str;
use Normalizer;

class TechnicalKeywordTaxonomy
{
    /**
     * @return array<string, array{
     *     category: string,
     *     aliases: list<string>,
     *     requires_context?: list<string>
     * }>
     */
    public static function definitions(): array
    {
        $definitions = [];

        foreach (self::categoryDefinitions() as $category => $terms) {
            foreach ($terms as $canonical => $definition) {
                $definitions[$canonical] = [
                    'category' => $category,
                    'aliases' => $definition['aliases'],
                    'requires_context' => $definition['requires_context'] ?? [],
                ];
            }
        }

        return $definitions;
    }

    public static function canonicalForExplicitSkill(string $skill): ?string
    {
        $normalizedSkill = self::normalizeText($skill);

        if ($normalizedSkill === null) {
            return null;
        }

        $matches = [];

        foreach (self::definitions() as $canonical => $definition) {
            foreach ($definition['aliases'] as $alias) {
                $normalizedAlias = self::normalizeText($alias);

                if ($normalizedAlias !== $normalizedSkill) {
                    continue;
                }

                $matches[] = [
                    'canonical' => $canonical,
                    'specificity' => strlen($normalizedAlias),
                ];
            }
        }

        if ($matches === []) {
            return null;
        }

        usort($matches, function (array $left, array $right): int {
            $specificityComparison = $right['specificity'] <=> $left['specificity'];

            if ($specificityComparison !== 0) {
                return $specificityComparison;
            }

            return strcmp($left['canonical'], $right['canonical']);
        });

        return $matches[0]['canonical'];
    }

    /**
     * @return array<string, array<string, array{
     *     aliases: list<string>,
     *     requires_context?: list<string>
     * }>>
     */
    private static function categoryDefinitions(): array
    {
        return [
            'programming_languages' => [
                'php' => ['aliases' => ['php']],
                'python' => ['aliases' => ['python']],
                'javascript' => ['aliases' => ['javascript', 'js']],
                'typescript' => ['aliases' => ['typescript', 'ts']],
                'java' => ['aliases' => ['java']],
                'csharp' => ['aliases' => ['c#', 'csharp']],
                'ruby' => ['aliases' => ['ruby']],
                'go' => [
                    'aliases' => ['golang', 'go'],
                    'requires_context' => ['backend', 'developer', 'engineer', 'golang', 'grpc', 'kubernetes', 'microservices', 'software'],
                ],
                'kotlin' => ['aliases' => ['kotlin']],
                'swift' => ['aliases' => ['swift']],
                'dart' => ['aliases' => ['dart']],
            ],
            'frontend_frameworks' => [
                'react' => ['aliases' => ['react', 'react.js', 'reactjs']],
                'vue' => ['aliases' => ['vue', 'vue.js', 'vuejs']],
                'angular' => ['aliases' => ['angular', 'angularjs']],
                'nextjs' => ['aliases' => ['nextjs', 'next.js']],
                'nuxtjs' => ['aliases' => ['nuxtjs', 'nuxt.js']],
                'tailwind' => ['aliases' => ['tailwind', 'tailwindcss']],
                'frontend' => ['aliases' => ['frontend', 'front-end', 'front end']],
            ],
            'backend_frameworks' => [
                'laravel' => ['aliases' => ['laravel']],
                'django' => ['aliases' => ['django']],
                'nestjs' => ['aliases' => ['nestjs', 'nest.js']],
                'express' => ['aliases' => ['express', 'express.js']],
                'fastapi' => ['aliases' => ['fastapi', 'fast api']],
                'spring' => ['aliases' => ['spring', 'spring boot']],
                'rails' => ['aliases' => ['rails', 'ruby on rails']],
                'dotnet' => ['aliases' => ['.net', 'dotnet', '.net core', 'asp.net', 'asp.net core']],
                'backend' => ['aliases' => ['backend', 'back-end', 'back end']],
                'fullstack' => ['aliases' => ['full stack', 'full-stack', 'fullstack']],
                'nodejs' => ['aliases' => ['node.js', 'nodejs', 'node js']],
            ],
            'databases' => [
                'sql' => ['aliases' => ['sql']],
                'mysql' => ['aliases' => ['mysql']],
                'postgresql' => ['aliases' => ['postgres', 'postgresql', 'postgre sql']],
                'mongodb' => ['aliases' => ['mongodb', 'mongo db']],
                'redis' => ['aliases' => ['redis']],
                'elasticsearch' => ['aliases' => ['elasticsearch', 'elastic search']],
            ],
            'cloud' => [
                'aws' => ['aliases' => ['aws', 'amazon web services']],
                'azure' => ['aliases' => ['azure']],
                'gcp' => ['aliases' => ['gcp', 'google cloud', 'google cloud platform']],
                'cloud' => ['aliases' => ['cloud', 'cloud native']],
            ],
            'devops' => [
                'docker' => ['aliases' => ['docker']],
                'kubernetes' => ['aliases' => ['kubernetes', 'k8s']],
                'terraform' => ['aliases' => ['terraform']],
                'ci_cd' => ['aliases' => ['ci/cd', 'ci cd', 'cicd', 'continuous integration', 'continuous delivery']],
                'git' => ['aliases' => ['git']],
                'github_actions' => ['aliases' => ['github actions']],
                'devops' => ['aliases' => ['devops', 'dev ops']],
                'observability' => ['aliases' => ['observability', 'observabilidade']],
            ],
            'testing' => [
                'testing' => ['aliases' => ['testing', 'testes', 'automated testing']],
                'unit_testing' => ['aliases' => ['unit testing', 'teste unitario', 'testes unitarios']],
                'integration_testing' => ['aliases' => ['integration testing', 'teste de integracao', 'testes de integracao']],
                'e2e_testing' => ['aliases' => ['e2e', 'end to end', 'end-to-end']],
                'playwright' => ['aliases' => ['playwright']],
                'cypress' => ['aliases' => ['cypress']],
                'jest' => ['aliases' => ['jest']],
                'phpunit' => ['aliases' => ['phpunit']],
                'pytest' => ['aliases' => ['pytest']],
                'qa' => ['aliases' => ['qa', 'quality assurance']],
                'robot_framework' => ['aliases' => ['robot framework']],
                'selenium' => ['aliases' => ['selenium']],
                'postman' => ['aliases' => ['postman']],
                'owasp' => ['aliases' => ['owasp']],
                'test_automation' => ['aliases' => ['test automation', 'automacao de testes']],
            ],
            'data' => [
                'data' => ['aliases' => ['data', 'dados']],
                'analytics' => ['aliases' => ['analytics', 'analitica', 'analitics']],
                'bi' => ['aliases' => ['bi', 'business intelligence']],
                'etl' => ['aliases' => ['etl']],
                'airflow' => ['aliases' => ['airflow']],
                'spark' => ['aliases' => ['spark', 'apache spark']],
                'pandas' => ['aliases' => ['pandas']],
                'data_engineering' => ['aliases' => ['data engineering', 'engenharia de dados']],
            ],
            'mobile' => [
                'react_native' => ['aliases' => ['react native']],
                'flutter' => ['aliases' => ['flutter']],
                'android' => ['aliases' => ['android']],
                'ios' => ['aliases' => ['ios']],
                'mobile' => ['aliases' => ['mobile']],
            ],
            'architecture' => [
                'microservices' => ['aliases' => ['microservices', 'micro services', 'micro-servicos', 'microservicos']],
                'middleware' => ['aliases' => ['middleware']],
                'rest_api' => [
                    'aliases' => ['rest', 'rest api', 'restful api', 'restful apis'],
                    'requires_context' => ['api', 'backend', 'endpoint', 'http', 'integration', 'microservices', 'service'],
                ],
                'graphql' => ['aliases' => ['graphql', 'graph ql']],
                'ddd' => ['aliases' => ['ddd', 'domain driven design', 'domain-driven design']],
                'clean_architecture' => ['aliases' => ['clean architecture']],
                'hexagonal_architecture' => ['aliases' => ['hexagonal architecture']],
                'event_driven' => ['aliases' => ['event driven', 'event-driven']],
                'api' => ['aliases' => ['api', 'apis']],
                'rpa' => ['aliases' => ['rpa', 'robotic process automation']],
            ],
            'ai_ml' => [
                'openai' => ['aliases' => ['openai', 'open ai']],
                'llm' => ['aliases' => ['llm', 'llms', 'large language model', 'large language models']],
                'nlp' => ['aliases' => ['nlp', 'natural language processing']],
                'machine_learning' => ['aliases' => ['machine learning', 'ml']],
                'ai_ml' => ['aliases' => ['artificial intelligence', 'ai/ml', 'ai ml', 'conversational ai', 'ai agent', 'ai agents', 'agentes de ia']],
                'chatbot' => ['aliases' => ['chatbot', 'chatbots', 'chat bot', 'chat bots']],
            ],
            'product_delivery' => [
                'automation' => ['aliases' => ['automation', 'automacao']],
                'communication' => ['aliases' => ['communication', 'comunicacao']],
                'product' => ['aliases' => ['product', 'produto']],
                'design' => ['aliases' => ['design']],
            ],
            'security' => [
                'fortinet' => ['aliases' => ['fortinet']],
            ],
        ];
    }

    private static function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = self::normalizeUnicode($value);
        $normalized = Str::of($normalized)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->value();

        return $normalized === '' ? null : $normalized;
    }

    private static function normalizeUnicode(string $text): string
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
