<?php

namespace App\Services\JobDiscovery;

use SimpleXMLElement;

class RemotiveDiscoverySource extends AbstractRssJobDiscoverySource
{
    public const SOURCE_KEY = 'remotive';

    protected function feedUrl(): string
    {
        return 'https://remotive.com/feed';
    }

    public function sourceKey(): string
    {
        return self::SOURCE_KEY;
    }

    public function sourceName(): string
    {
        return 'Remotive';
    }

    protected function softwareSignals(): array
    {
        return ['developer', 'engineer', 'software', 'backend', 'frontend', 'full stack', 'full-stack', 'php', 'laravel', 'vue', 'python', 'django', 'node', 'devops', 'qa', 'data'];
    }

    protected function entryFromItem(SimpleXMLElement $item): ?array
    {
        $detailUrl = $this->nullableString((string) $item->link);
        $title = $this->nullableString((string) $item->title);
        $descriptionText = $this->normalizedExcerpt((string) $item->description);

        if ($detailUrl === null || $title === null) {
            return null;
        }

        [$jobTitle, $companyName] = $this->splitTitleAndCompany($title);
        $categoryText = $this->categoryText($item);
        $matchingText = implode(' ', array_filter([$title, $descriptionText, $categoryText]));

        if (! $this->softwareTextMatches($matchingText)) {
            return null;
        }

        $location = $this->listingLocation($descriptionText, $categoryText);
        $workMode = $this->nullableWorkMode(implode(' ', array_filter([$descriptionText, $categoryText, $location])));

        return [
            'detail_url' => $detailUrl,
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'location' => $location,
            'work_mode' => $workMode,
            'description_text' => $descriptionText,
        ];
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function splitTitleAndCompany(string $title): array
    {
        $parts = preg_split('/\s+at\s+/i', $title);

        if (! is_array($parts) || count($parts) < 2) {
            return [$title, null];
        }

        $companyName = array_pop($parts);
        $jobTitle = implode(' at ', $parts);

        return [$this->nullableString($jobTitle), $this->nullableString($companyName)];
    }

    private function categoryText(SimpleXMLElement $item): ?string
    {
        $categories = [];

        foreach ($item->category as $category) {
            $value = $this->nullableString((string) $category);

            if ($value !== null) {
                $categories[] = $value;
            }
        }

        if ($categories === []) {
            return null;
        }

        return implode(' ', $categories);
    }

    private function listingLocation(?string $descriptionText, ?string $categoryText): ?string
    {
        $text = implode(' ', array_filter([$descriptionText, $categoryText]));

        if ($text === '') {
            return null;
        }

        if (preg_match('/Location:\s*([^|]+)/i', $text, $matches) === 1) {
            return $this->nullableString($matches[1]);
        }

        if (preg_match('/Remote Location\s*([^|]+)/i', $text, $matches) === 1) {
            return $this->nullableString($matches[1]);
        }

        return null;
    }
}
