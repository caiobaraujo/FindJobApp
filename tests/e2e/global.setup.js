import { execSync } from 'node:child_process';

export default async function globalSetup() {
    execSync('php artisan app:prepare-e2e-discovery-user --email=e2e@example.com --password=password --name="E2E User"', {
        cwd: process.cwd(),
        stdio: 'inherit',
        env: {
            ...process.env,
            APP_ENV: 'local',
            JOB_DISCOVERY_USE_FIXTURES: 'true',
        },
    });
}
