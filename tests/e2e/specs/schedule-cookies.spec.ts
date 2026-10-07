import { test, expect, type BrowserContext } from '@playwright/test';

// Layout cookie values: 0 standard, 1 wide, 2 tall, 3 week.
const WEEK = 'schedule.php?sd=2030-03-04';
const LAYOUT_COOKIE = 'schedule-style-1';
const CALENDAR_COOKIE = 'schedule_calendar_toggle';

async function cookieValue(context: BrowserContext, name: string) {
  return (await context.cookies()).find((cookie) => cookie.name === name)?.value;
}

test.describe('layout cookie', () => {
  test('choosing a layout stores it in the cookie', async ({ page, context }) => {
    await page.goto(WEEK);
    expect(await cookieValue(context, LAYOUT_COOKIE)).toBeUndefined();

    await page.locator('#schedule_wide').click();
    await expect(page.locator('#schedule_wide')).toHaveClass(/active/);

    expect(await cookieValue(context, LAYOUT_COOKIE)).toBe('1');
  });

  test('the layout is kept after a reload and on a new page', async ({ page, context }) => {
    await page.goto(WEEK);
    await page.locator('#schedule_tall').click();
    await expect(page.locator('#schedule_tall')).toHaveClass(/active/);

    await page.reload();
    await expect(page.locator('#schedule_tall')).toHaveClass(/active/);

    const another = await context.newPage();
    await another.goto(WEEK);
    await expect(another.locator('#schedule_tall')).toHaveClass(/active/);
  });

  test('a stored layout is applied without clicking', async ({ page, context, baseURL }) => {
    await context.addCookies([{ name: LAYOUT_COOKIE, value: '3', url: baseURL! }]);

    await page.goto(WEEK);

    await expect(page.locator('#schedule_week')).toHaveClass(/active/);
    await expect(page.locator('#reservations table.condensed')).toHaveCount(1);
  });

  test('an unknown layout value falls back to the standard layout', async ({ page, context, baseURL }) => {
    await context.addCookies([{ name: LAYOUT_COOKIE, value: '99', url: baseURL! }]);

    await page.goto(WEEK);

    await expect(page.locator('#schedule_standard')).toHaveClass(/active/);
    await expect(page.locator('#reservations table.reservations')).toHaveCount(7);
  });
});

test.describe('date picker cookie', () => {
  test('opening and closing the picker is stored in the cookie', async ({ page, context }) => {
    await page.goto(WEEK);

    await page.locator('#calendar_toggle').click();
    await expect(page.locator('#datepicker')).toHaveClass(/show/);
    expect(await cookieValue(context, CALENDAR_COOKIE)).toBe('true');

    await page.locator('#calendar_toggle').click();
    await expect(page.locator('#datepicker')).not.toHaveClass(/show/);
    expect(await cookieValue(context, CALENDAR_COOKIE)).toBe('false');
  });

  test('the picker stays open after a reload', async ({ page }) => {
    await page.goto(WEEK);
    await page.locator('#calendar_toggle').click();
    await expect(page.locator('#datepicker')).toHaveClass(/show/);

    await page.reload();

    await expect(page.locator('#datepicker')).toHaveClass(/show/);
  });
});
