import { test, expect, type Page } from '@playwright/test';
import { resetConfig, setConfig } from '../support/config';
import { logIn } from '../support/login';

test.use({ storageState: { cookies: [], origins: [] } });

const PASSWORD = 'E2e-Passw0rd!';
const CHANGED_PASSWORD = 'E2e-Passw0rd!2';

async function changePassword(page: Page, current: string, next: string, confirm = next) {
  await page.goto('password.php');
  const fields = page.locator('#password-reset-form input[type="password"]');
  await fields.nth(0).fill(current);
  await fields.nth(1).fill(next);
  await fields.nth(2).fill(confirm);
  await page.locator('#password-reset-form button[type="submit"]').click();
}

test.describe('password', () => {
  test('the login page links to a form that asks for an email address', async ({ page }) => {
    await page.goto('index.php');
    await page.locator('#forgot-password a').click();

    await expect(page).toHaveURL(/forgot\.php/);
    await expect(page.locator('#forgotbox input')).toHaveCount(1);
    await expect(page.locator('#forgotbox button')).toBeVisible();
  });

  test('a user can change their own password', async ({ page }) => {
    await logIn(page, 'e2e.password', PASSWORD);
    await expect(page.locator('#page-dashboard')).toBeVisible();

    await changePassword(page, PASSWORD, CHANGED_PASSWORD);
    await expect(page.locator('#password-reset-box .alert-success')).toContainText(
      'Your password has been changed successfully'
    );

    await page.goto('logout.php');
    await logIn(page, 'e2e.password', PASSWORD);
    await expect(page.locator('#loginError')).toBeVisible();
    await logIn(page, 'e2e.password', CHANGED_PASSWORD);
    await expect(page.locator('#page-dashboard')).toBeVisible();

    await changePassword(page, CHANGED_PASSWORD, PASSWORD);
    await expect(page.locator('#password-reset-box .alert-success')).toBeVisible();
  });

  test('a wrong current password or a different confirmation is rejected', async ({ page }) => {
    await logIn(page, 'e2e.password', PASSWORD);
    await expect(page.locator('#page-dashboard')).toBeVisible();

    await changePassword(page, 'Wrong-Passw0rd!', CHANGED_PASSWORD);
    await expect(page.locator('#password-reset-box')).toContainText('Current password is incorrect');

    await changePassword(page, PASSWORD, CHANGED_PASSWORD, 'Different-Passw0rd!');
    await expect(page.locator('#password-reset-box')).toContainText('Password confirmation must match password');
    await expect(page.locator('#password-reset-box .alert-success')).toHaveCount(0);

    await page.goto('logout.php');
    await logIn(page, 'e2e.password', PASSWORD);
    await expect(page.locator('#page-dashboard')).toBeVisible();
  });
});

test.describe('password reset disabled', () => {
  test.beforeAll(() => setConfig({ 'password.disable.reset': true }));
  test.afterAll(() => resetConfig());

  test('the login page has no forgot password link and the page is disabled', async ({ page }) => {
    await page.goto('index.php');
    await expect(page.locator('#page-login')).toBeVisible();
    await expect(page.locator('#forgot-password a')).toHaveCount(0);

    await page.goto('forgot.php');
    await expect(page.locator('#forgotbox')).toContainText('Disabled');
    await expect(page.locator('#forgotbox input')).toHaveCount(0);
    await expect(page.locator('#forgotbox button')).toHaveCount(0);
  });

  test('a user cannot change their password', async ({ page }) => {
    await logIn(page, 'e2e.password', PASSWORD);
    await expect(page.locator('#page-dashboard')).toBeVisible();

    await page.goto('password.php');
    await expect(page.locator('.alert')).toContainText('controlled by an external system');
    await expect(page.locator('#password-reset-form')).toHaveCount(0);
  });
});
