<?php

namespace App\Services\JobDiscovery;

use SimpleXMLElement;

class WeWorkRemotelyDiscoverySource extends AbstractRssJobDiscoverySource
{
    public const SOURCE_KEY = 'we-work-remotely';

    protected function feedUrl(): string
    {
        return 'https://weworkremotely.com/categories/remote-programming-jobs.rss';
    }

    public function sourceKey(): string
    {
        return self::SOURCE_KEY;
    }

    public function sourceName(): string
    {
        return 'We Work Remotely';
    }

    protected function softwareSignals(): array
    {
        return ['developer', 'engineer', 'software', 'backend', 'frontend', 'full stack', 'full-stack', 'php', 'laravel', 'vue', 'python', 'django', 'node'];
    }

    protected function entryFromItem(SimpleXMLElement $item): ?array
    {
        $detailUrl = $this->nullableString((string) $item->link);
        $title = $this->nullableString((string) $item->title);
        $descriptionText = $this->normalizedExcerpt((string) $item->description);
        $companyName = $this->creatorName($item);

        if ($detailUrl === null || $title === null) {
            return null;
        }

        [$jobTitle, $parsedCompanyName] = $this->splitTitleAndCompany($title);
        $companyName ??= $parsedCompanyName;

        if (! $this->softwareTextMatches(implode(' ', array_filter([$jobTitle, $descriptionText])))) {
            return null;
        }

        $location = $this->listingLocation($descriptionText);
        $workMode = $this->nullableWorkMode(implode(' ', array_filter([$title, $descriptionText, $location])));

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

        if (is_array($parts) && count($parts) >= 2) {
            $companyName = array_pop($parts);
            $jobTitle = implode(' at ', $parts);

            return [$this->nullableString($jobTitle), $this->nullableString($companyName)];
        }

        $segments = explode(':', $title, 2);

        if (count($segments) === 2) {
            return [
                $this->nullableString($segments[1]),
                $this->nullableString($segments[0]),
            ];
        }

        return [$title, null];
    }

    private function creatorName(SimpleXMLElement $item): ?string
    {
        $dcNamespace = $item->children('http://purl.org/dc/elements/1.1/');

        return $this->nullableString((string) ($dcNamespace->creator ?? ''));
    }

    private function listingLocation(?string $descriptionText): ?string
    {
        if ($descriptionText === null) {
            return null;
        }

        if (preg_match('/Location:\s*([^|]+)/i', $descriptionText, $matches) !== 1) {
            return null;
        }

        return $this->nullableString($matches[1]);
    }
}
