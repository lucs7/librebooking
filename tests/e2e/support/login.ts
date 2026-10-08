import type { Page } from '@playwright/test';

export async function logIn(page: Page, username: string, password = 'e2e-password') {
  await page.goto('index.php');
  await page.locator('#email').fill(username);
  await page.locator('#password').fill(password);
  await page.locator('#login button[type="submit"]').click();
}
