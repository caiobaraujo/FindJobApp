<?php

use App\Models\JobLead;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_leads', function (Blueprint $table): void {
            $table->string('normalized_source_url')->nullable()->after('source_url');
            $table->string('source_host')->nullable()->after('normalized_source_url');
            $table->index(['user_id', 'normalized_source_url']);
            $table->index(['user_id', 'source_host']);
        });

        JobLead::query()
            ->whereNotNull('source_url')
            ->each(function (JobLead $jobLead): void {
                $metadata = $this->canonicalSourceMetadata($jobLead->source_url);

                $jobLead->forceFill($metadata)->save();
            });
    }

    public function down(): void
    {
        Schema::table('job_leads', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'normalized_source_url']);
            $table->dropIndex(['user_id', 'source_host']);
            $table->dropColumn([
                'normalized_source_url',
                'source_host',
            ]);
        });
    }
    
    /**
     * @return array{normalized_source_url: string|null, source_host: string|null}
     */
    private function canonicalSourceMetadata(?string $sourceUrl): array
    {
        if ($sourceUrl === null || trim($sourceUrl) === '') {
            return [
                'normalized_source_url' => null,
                'source_host' => null,
            ];
        }

        $normalizedSourceUrl = $this->canonicalSourceUrl($sourceUrl);

        if ($normalizedSourceUrl === null) {
            return [
                'normalized_source_url' => null,
                'source_host' => null,
            ];
        }

        return [
            'normalized_source_url' => $normalizedSourceUrl,
            'source_host' => $this->canonicalSourceHost($normalizedSourceUrl),
        ];
    }

    private function canonicalSourceUrl(string $sourceUrl): ?string
    {
        $parts = parse_url($sourceUrl);

        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');

        if ($scheme === '' || $host === '') {
            return null;
        }

        $path = $parts['path'] ?? '/';
        $path = $path === '' ? '/' : preg_replace('#/+#', '/', $path);
        $path = is_string($path) ? $path : '/';

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return "{$scheme}://{$host}{$port}{$path}";
    }

    private function canonicalSourceHost(string $normalizedSourceUrl): ?string
    {
        $host = parse_url($normalizedSourceUrl, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace('/^www\./', '', strtolower($host)) ?? strtolower($host);
    }
};
