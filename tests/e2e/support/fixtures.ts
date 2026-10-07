import { test as base, type Browser, type Page } from '@playwright/test';
import { authFile } from './auth';

// `page` is the admin; `userPage` is e2e.user (direct permission) and
// `groupUserPage` is e2e.group (group permission), each in its own context.
async function pageFor(browser: Browser, baseURL: string | undefined, file: string) {
  const context = await browser.newContext({ baseURL, storageState: file });
  return { context, page: await context.newPage() };
}

export const test = base.extend<{ userPage: Page; groupUserPage: Page }>({
  userPage: async ({ browser, baseURL }, use) => {
    const { context, page } = await pageFor(browser, baseURL, authFile('e2e-user.json'));
    await use(page);
    await context.close();
  },
  groupUserPage: async ({ browser, baseURL }, use) => {
    const { context, page } = await pageFor(browser, baseURL, authFile('e2e-group.json'));
    await use(page);
    await context.close();
  },
});

export { expect } from '@playwright/test';
