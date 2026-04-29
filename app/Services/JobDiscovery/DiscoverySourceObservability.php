<?php

namespace App\Services\JobDiscovery;

use App\Models\JobLead;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class DiscoverySourceObservability
{
    /**
     * @param Collection<int, JobLead> $jobLeads
     * @return array{ready: int, limited: int, missing_description: int, missing_keywords: int}
     */
    public function analysisCounts(Collection $jobLeads): array
    {
        $readyCount = $jobLeads
            ->filter(fn (JobLead $jobLead): bool => ! $jobLead->hasLimitedAnalysis())
            ->count();
        $missingDescriptionCount = $jobLeads
            ->filter(fn (JobLead $jobLead): bool => ! Str::of((string) $jobLead->description_text)->trim()->isNotEmpty())
            ->count();
        $missingKeywordsCount = $jobLeads
            ->filter(fn (JobLead $jobLead): bool => ($jobLead->extracted_keywords ?? []) === [])
            ->count();

        return [
            'ready' => $readyCount,
            'limited' => $jobLeads->count() - $readyCount,
            'missing_description' => $missingDescriptionCount,
            'missing_keywords' => $missingKeywordsCount,
        ];
    }

    /**
     * @param Collection<int, JobLead> $createdJobLeads
     * @param list<array<string, mixed>> $sources
     * @return list<array<string, mixed>>
     */
    public function summarizeSources(Collection $createdJobLeads, array $sources): array
    {
        $observability = [];

        foreach ($sources as $sourceSummary) {
            $sourceKey = (string) ($sourceSummary['source'] ?? '');
            $sourceName = (string) ($sourceSummary['source_name'] ?? $sourceKey);
            $sourceJobLeads = $createdJobLeads
                ->filter(fn (JobLead $jobLead): bool => $jobLead->source_name === $sourceName)
                ->values();
            $visibleByDefaultCount = $sourceJobLeads
                ->filter(fn (JobLead $jobLead): bool => $jobLead->lead_status !== JobLead::STATUS_IGNORED)
                ->filter(fn (JobLead $jobLead): bool => $jobLead->locationClassification() !== JobLead::LOCATION_CLASSIFICATION_INTERNATIONAL)
                ->count();
            $analysis = $this->analysisCounts($sourceJobLeads);

            $observability[] = [
                ...$sourceSummary,
                'imported' => $sourceJobLeads->count(),
                'visible_by_default' => $visibleByDefaultCount,
                'hidden_by_default' => $sourceJobLeads->count() - $visibleByDefaultCount,
                'ready_analysis' => $analysis['ready'],
                'limited_analysis' => $analysis['limited'],
                'missing_description' => $analysis['missing_description'],
                'missing_keywords' => $analysis['missing_keywords'],
            ];
        }

        return $observability;
    }
}
