<?php

namespace App\Console\Commands;

use App\Models\JobLead;
use App\Models\User;
use App\Services\JobLeadImportService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class ImportPostJobLead extends Command
{
    protected $signature = 'job-leads:import-post
        {user_id}
        {--platform=}
        {--post-url=}
        {--job-url=}
        {--author=}
        {--company=}
        {--title=}
        {--text=}';

    protected $description = 'Create a JobLead from public post text without inventing missing job details.';

    public function handle(JobLeadImportService $jobLeadImportService): int
    {
        $user = User::query()->find($this->argument('user_id'));

        if ($user === null) {
            $this->error('User not found.');

            return SymfonyCommand::FAILURE;
        }

        $postUrl = $this->stringOption('post-url');

        if ($postUrl === null) {
            $this->error('The --post-url option is required.');

            return SymfonyCommand::FAILURE;
        }

        $result = $jobLeadImportService->importForUser($user->id, $this->stringOption('job-url'), [
            'source_type' => JobLead::SOURCE_TYPE_POST,
            'source_platform' => $this->stringOption('platform'),
            'source_post_url' => $postUrl,
            'source_author' => $this->stringOption('author'),
            'source_context_text' => $this->stringOption('text'),
            'description_text' => $this->stringOption('text'),
            'company_name' => $this->stringOption('company'),
            'job_title' => $this->stringOption('title'),
        ]);

        if ($result['status'] === JobLeadImportService::STATUS_DUPLICATE) {
            $this->line('Duplicate skipped.');

            return SymfonyCommand::SUCCESS;
        }

        if ($result['status'] === JobLeadImportService::STATUS_INVALID) {
            $this->error('A valid --post-url or --job-url is required.');

            return SymfonyCommand::FAILURE;
        }

        $jobLead = $result['job_lead'];

        if ($jobLead === null) {
            $this->error('Job lead import failed.');

            return SymfonyCommand::FAILURE;
        }

        $this->line(sprintf('Created JobLead #%d', $jobLead->id));
        $this->line(sprintf('Source post: %s', $jobLead->source_post_url ?? ''));
        $this->line(sprintf('Job URL: %s', $jobLead->source_url ?? ''));
        $this->line(sprintf('Keywords: %s', implode(', ', $jobLead->extracted_keywords ?? [])));

        return SymfonyCommand::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
