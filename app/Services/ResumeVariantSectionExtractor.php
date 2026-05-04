<?php

namespace App\Services;

class ResumeVariantSectionExtractor
{
    /**
     * @return list<array{key: string, title: string, body: string}>
     */
    public function extract(string $text): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
        $sections = [];
        $currentKey = null;
        $buffer = [];

        $pushSection = function () use (&$sections, &$currentKey, &$buffer): void {
            if ($currentKey === null) {
                $buffer = [];

                return;
            }

            $body = trim(implode("\n", $buffer));

            if ($body !== '') {
                $sections[] = [
                    'key' => $currentKey,
                    'title' => $this->sectionDefinitions()[$currentKey],
                    'body' => $body,
                ];
            }

            $buffer = [];
        };

        foreach ($lines as $line) {
            $headingKey = $this->headingKey((string) $line);

            if ($headingKey !== null) {
                $pushSection();
                $currentKey = $headingKey;

                continue;
            }

            if ($currentKey !== null) {
                $buffer[] = (string) $line;
            }
        }

        $pushSection();

        return $sections;
    }

    private function headingKey(string $line): ?string
    {
        $normalizedLine = $this->normalizeHeading($line);

        foreach ($this->sectionDefinitions() as $key => $title) {
            if ($normalizedLine === $this->normalizeHeading($title)) {
                return $key;
            }
        }

        return null;
    }

    private function normalizeHeading(string $line): string
    {
        return strtolower(rtrim(trim($line), ':'));
    }

    /**
     * @return array<string, string>
     */
    private function sectionDefinitions(): array
    {
        return [
            'summary' => 'Summary',
            'core_skills' => 'Core Skills',
            'professional_experience' => 'Professional Experience',
            'target_role_alignment' => 'Target Role Alignment',
        ];
    }
}
