<?php

namespace App\Console\Commands;

use App\Models\JobLead;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class PrepareE2eDiscoveryUser extends Command
{
    protected $signature = 'app:prepare-e2e-discovery-user
        {--email=e2e@example.com}
        {--password=password}
        {--name=E2E User}';

    protected $description = 'Create or reset a deterministic user for the discovery E2E smoke test.';

    public function handle(): int
    {
        $email = $this->stringOption('email');
        $password = $this->stringOption('password');
        $name = $this->stringOption('name');

        if ($email === null || $password === null || $name === null) {
            $this->error('Email, password, and name are required.');

            return SymfonyCommand::FAILURE;
        }

        $user = User::query()->firstOrNew([
            'email' => $email,
        ]);

        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ])->save();

        JobLead::query()
            ->where('user_id', $user->id)
            ->delete();

        UserProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'base_resume_text' => 'Laravel PHP Vue JavaScript frontend backend full stack developer.',
                'core_skills' => ['Laravel', 'PHP', 'Vue', 'JavaScript', 'Frontend', 'Backend', 'Full stack'],
                'auto_discover_jobs' => false,
                'last_discovered_at' => null,
                'last_discovered_new_count' => null,
                'last_discovery_batch_id' => null,
            ],
        );

        $this->line(sprintf('Prepared E2E user #%d (%s).', $user->id, $email));

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
