<?php

namespace App\Services\JobDiscovery;

interface JobDiscoverySource
{
    public function sourceKey(): string;

    public function sourceName(): string;

    /**
     * @return array{
     *     status_code: int,
     *     candidate_links: int,
     *     parsed_jobs: int,
     *     invalid_links: int,
     *     entries: list<array<string, mixed>>
     * }
     */
    public function discoverEntriesWithDiagnostics(): array;

    /**
     * @param array<string, mixed> $entry
     * @return array{
     *     source_url: string|null,
     *     job_title: string|null,
     *     company_name: string|null,
     *     location: string|null,
     *     work_mode?: string|null,
     *     description_text: string|null
     * }
     */
    public function enrichEntry(array $entry): array;
}
