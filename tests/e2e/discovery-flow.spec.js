import { expect, test } from '@playwright/test';

const email = 'e2e@example.com';
const password = 'password';

test('discovery flow shows newly found jobs when viewing the latest batch', async ({ page }) => {
    await page.goto('/login');

    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: 'Log in' }).click();

    await page.waitForURL(/dashboard|job-leads/);
    await page.goto('/job-leads');

    await page.getByTestId('discovery-search-input').fill('Laravel');
    await page.getByTestId('find-jobs-button').click();

    await expect(page.getByTestId('find-jobs-button')).toHaveText('Find jobs', { timeout: 20000 });
    const discoveryResult = page.getByTestId('discovery-result');

    await expect(discoveryResult).toBeVisible();
    await expect(discoveryResult.getByText(/new job[s]? found\./i)).toBeVisible();

    const viewNewJobsLink = page.getByTestId('view-new-jobs-link');

    await expect(viewNewJobsLink).toHaveAttribute('href', /discovery_batch=latest/);
    await expect(viewNewJobsLink).toHaveAttribute('href', /location_scope=all/);

    await viewNewJobsLink.click();

    await expect(page).toHaveURL(/discovery_batch=latest/);
    await expect(page).toHaveURL(/location_scope=all/);
    await expect(page.getByTestId('job-lead-card').first()).toBeVisible();
    await expect(page.getByText('No matched jobs yet')).toHaveCount(0);
});
