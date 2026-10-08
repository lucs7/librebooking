import { test, expect, type Page } from '@playwright/test';
import { logIn } from '../support/login';

// These specs log in themselves, each as its own user, and undo their changes.
test.use({ storageState: { cookies: [], origins: [] } });

async function saveProfile(page: Page) {
  await page.locator('#btnUpdate').click();
  await expect(page.locator('#profileUpdatedMessage')).toBeVisible();
}

async function openProfile(page: Page) {
  await page.goto('profile.php');
  await expect(page.locator('#form-profile')).toBeVisible();
}

test.describe('profile', () => {
  test.beforeEach(async ({ page }) => {
    await logIn(page, 'e2e.profile');
    await expect(page.locator('#page-dashboard')).toBeVisible();
    await openProfile(page);
  });

  test('a user can change their personal data', async ({ page }) => {
    await page.locator('input#fname').fill('Erika');
    await page.locator('input#lname').fill('Muster');
    await page.locator('input#phone').fill('+49 30 123456');
    await page.locator('#txtOrganization').fill('Muster GmbH');
    await page.locator('#txtPosition').fill('Lead');
    await saveProfile(page);

    await openProfile(page);
    await expect(page.locator('input#fname')).toHaveValue('Erika');
    await expect(page.locator('input#lname')).toHaveValue('Muster');
    await expect(page.locator('input#phone')).toHaveValue('+49 30 123456');
    await expect(page.locator('#txtOrganization')).toHaveValue('Muster GmbH');
    await expect(page.locator('#txtPosition')).toHaveValue('Lead');

    await page.locator('input#fname').fill('E2E');
    await page.locator('input#lname').fill('Profile');
    await page.locator('input#phone').fill('');
    await page.locator('#txtOrganization').fill('E2E Org');
    await page.locator('#txtPosition').fill('');
    await saveProfile(page);
  });

  test('the time zone is saved', async ({ page }) => {
    await page.locator('#timezoneDropDown').selectOption('Europe/Berlin');
    await saveProfile(page);

    await openProfile(page);
    await expect(page.locator('#timezoneDropDown')).toHaveValue('Europe/Berlin');

    await page.locator('#timezoneDropDown').selectOption('America/New_York');
    await saveProfile(page);
  });

  test('the default page decides where the user lands after logging in', async ({ page }) => {
    await page.locator('#homepage').selectOption({ label: 'Schedule' });
    await saveProfile(page);

    await page.goto('logout.php');
    await logIn(page, 'e2e.profile');
    await expect(page).toHaveURL(/schedule\.php/);
    await expect(page.locator('#page-schedule')).toBeVisible();

    await openProfile(page);
    await page.locator('#homepage').selectOption({ label: 'Dashboard' });
    await saveProfile(page);
  });

  test('a changed user name is used for the next login', async ({ page }) => {
    await page.locator('input#username').fill('e2e.profile.renamed');
    await saveProfile(page);

    await page.goto('logout.php');
    await logIn(page, 'e2e.profile');
    await expect(page.locator('#loginError')).toBeVisible();
    await logIn(page, 'e2e.profile.renamed');
    await expect(page.locator('#page-dashboard')).toBeVisible();

    await openProfile(page);
    await page.locator('input#username').fill('e2e.profile');
    await saveProfile(page);
  });

  test('data that is invalid or already taken is not saved', async ({ page }) => {
    await page.locator('input#fname').fill('');
    await expect(page.locator('#btnUpdate')).toBeDisabled();
    await expect(
      page.locator('.form-group', { has: page.locator('input#fname') }).getByText('First name is required.')
    ).toBeVisible();

    await openProfile(page);
    await page.locator('input#email').fill('not-an-email');
    await expect(page.locator('#btnUpdate')).toBeDisabled();

    await openProfile(page);
    await page.locator('input#email').fill('admin@example.com');
    await page.locator('#btnUpdate').click();
    await expect(page.locator('#validationErrors')).toContainText('That email address is already registered.');
    await expect(page.locator('#profileUpdatedMessage')).toBeHidden();

    await openProfile(page);
    await page.locator('input#username').fill('admin');
    await page.locator('#btnUpdate').click();
    await expect(page.locator('#validationErrors')).toContainText('That user name is already registered.');

    await openProfile(page);
    await expect(page.locator('input#username')).toHaveValue('e2e.profile');
    await expect(page.locator('input#email')).toHaveValue('e2e.profile@example.com');
  });
});
