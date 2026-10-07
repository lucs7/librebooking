import { test, expect, type BrowserContext, type Page } from '@playwright/test';

test.use({ storageState: { cookies: [], origins: [] } });

async function logIn(page: Page, { remember = false } = {}) {
  await page.goto('index.php');
  await page.locator('#email').fill('admin');
  await page.locator('#password').fill('password');
  if (remember) {
    await page.locator('#rememberMe').check();
  }
  await page.locator('#login button[type="submit"]').click();
  await expect(page.locator('#page-dashboard')).toBeVisible();
}

async function getCookie(context: BrowserContext, name: string) {
  return (await context.cookies()).find((cookie) => cookie.name === name);
}

test('login page shows the sign-in form', async ({ page }) => {
  await page.goto('index.php');
  await expect(page.locator('#page-login')).toBeVisible();
  await expect(page.locator('#email')).toBeVisible();
  await expect(page.locator('#password')).toBeVisible();
});

test('wrong password keeps the visitor on the login page', async ({ page }) => {
  await page.goto('index.php');
  await page.locator('#email').fill('admin');
  await page.locator('#password').fill('not-the-password');
  await page.locator('#login button[type="submit"]').click();

  await expect(page.locator('#page-login')).toBeVisible();
  await expect(page.locator('#page-dashboard')).toHaveCount(0);
});

for (const target of ['dashboard.php', 'schedule.php']) {
  test(`${target} redirects a visitor to the login page`, async ({ page }) => {
    await page.goto(target);

    await expect(page).toHaveURL(/index\.php\?redirect=/);
    await expect(page.locator('#page-login')).toBeVisible();
    expect(new URL(page.url()).searchParams.get('redirect')).toContain(target);
  });
}

test('logging in after a redirect returns to the requested page', async ({ page }) => {
  await page.goto('schedule.php');
  await page.locator('#email').fill('admin');
  await page.locator('#password').fill('password');
  await page.locator('#login button[type="submit"]').click();

  await expect(page).toHaveURL(/schedule\.php/);
  await expect(page.locator('#page-schedule')).toBeVisible();
});

test.describe('login cookies', () => {
  test('logging in sets the session cookie for the application path', async ({ page, context, baseURL }) => {
    await logIn(page);

    const session = await getCookie(context, 'PHPSESSID');
    expect(session?.path).toBe(new URL(baseURL!).pathname.replace(/\/$/, ''));
    expect(await getCookie(context, 'persist_login')).toBeUndefined();
  });

  test('remember me stores a login cookie that lasts about 30 days', async ({ page, context }) => {
    await logIn(page, { remember: true });

    const login = await getCookie(context, 'persist_login');
    expect(login?.httpOnly).toBe(true);
    expect(login?.sameSite).toBe('Lax');
    const days = (login!.expires - Date.now() / 1000) / 86400;
    expect(days).toBeGreaterThan(29);
    expect(days).toBeLessThanOrEqual(30);
  });

  test('the login cookie signs a visitor in again after the session cookie is lost', async ({ page, context }) => {
    await logIn(page, { remember: true });
    await context.clearCookies({ name: 'PHPSESSID' });

    await page.goto('dashboard.php');

    await expect(page.locator('#page-dashboard')).toBeVisible();
    expect(await getCookie(context, 'persist_login')).toBeDefined();
  });

  test('without remember me a lost session cookie means logging in again', async ({ page, context }) => {
    await logIn(page);
    await context.clearCookies({ name: 'PHPSESSID' });

    await page.goto('dashboard.php');

    await expect(page.locator('#page-login')).toBeVisible();
  });
});

test.describe('logout', () => {
  test('the sign out link ends the session', async ({ page }) => {
    await logIn(page);

    await page.getByRole('link', { name: 'Sign Out' }).click();

    await expect(page).toHaveURL(/index\.php/);
    await expect(page.locator('#page-login')).toBeVisible();

    await page.goto('dashboard.php');
    await expect(page).toHaveURL(/index\.php\?redirect=/);
    await expect(page.locator('#page-login')).toBeVisible();
  });

  test('logging out removes the login cookie', async ({ page, context }) => {
    await logIn(page, { remember: true });
    expect(await getCookie(context, 'persist_login')).toBeDefined();

    await page.goto('logout.php');

    await expect(page.locator('#page-login')).toBeVisible();
    expect(await getCookie(context, 'persist_login')).toBeUndefined();

    await page.goto('dashboard.php');
    await expect(page.locator('#page-login')).toBeVisible();
  });

  test('logging out without being logged in shows the login page', async ({ page }) => {
    await page.goto('logout.php');

    await expect(page).toHaveURL(/index\.php/);
    await expect(page.locator('#page-login')).toBeVisible();
  });
});
