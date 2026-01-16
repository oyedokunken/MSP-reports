import { test, expect } from '@playwright/test';

test.describe('Authentication', () => {
    test('should show login page', async ({ page }) => {
        await page.goto('/login');
        await expect(page.getByRole('heading', { name: 'Sign In' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Sign in', exact: true })).toBeVisible();
    });

    test('should fail with invalid credentials', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel(/Email/i).fill('invalid@example.com');
        await page.getByLabel(/Password/i).fill('wrongpassword');
        await page.getByRole('button', { name: 'Sign in', exact: true }).click();

        // Check for error toast or message
        await expect(page.getByText(/Invalid credentials/i).or(page.getByText(/Error/i))).toBeVisible();
    });
});
