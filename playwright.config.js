import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    reporter: [['html', { open: 'never' }]],
    use: {
        baseURL: 'http://127.0.0.1:8000',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        video: 'retain-on-failure',
    },
    globalSetup: './tests/e2e/global.setup.js',
    webServer: [
        {
            command: 'php artisan serve --host=127.0.0.1 --port=8000',
            port: 8000,
            reuseExistingServer: true,
            env: {
                ...process.env,
                APP_ENV: 'local',
                JOB_DISCOVERY_USE_FIXTURES: 'true',
            },
            timeout: 120000,
        },
        {
            command: 'npm run dev -- --host 127.0.0.1 --port 5173',
            port: 5173,
            reuseExistingServer: true,
            timeout: 120000,
        },
    ],
});
