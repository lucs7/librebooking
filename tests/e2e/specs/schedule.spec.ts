import { test, expect, devices, type Page } from '@playwright/test';

// A fixed week keeps the specs independent of today's date (Monday, shown Sunday to Saturday).
const WEEK = 'schedule.php?sd=2030-03-04';
const WEEK_RANGE = '03/03/2030 - 03/09/2030';

test.describe('schedule page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('schedule.php');
    await expect(page.locator('#page-schedule')).toBeVisible();
  });

  test('lists the sample resources', async ({ page }) => {
    const names = page.locator('#reservations a.resourceNameSelector');
    await expect(names.filter({ hasText: 'Conference Room 1' }).first()).toBeVisible();
    await expect(names.filter({ hasText: 'Conference Room 2' }).first()).toBeVisible();
  });

  test('next period changes the displayed dates', async ({ page }) => {
    const dates = page.locator('.schedule-dates').first();
    const before = await dates.innerText();

    await page.locator('.schedule-dates a.change-date:has(.bi-arrow-right-circle-fill)').first().click();

    await expect(page.locator('.schedule-dates').first()).not.toHaveText(before);
    await expect(page.locator('#page-schedule')).toBeVisible();
  });
});

test.describe('layouts', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(WEEK);
    await expect(page.locator('#page-schedule')).toBeVisible();
  });

  test('standard shows one table per day with a row per resource', async ({ page }) => {
    await expect(page.locator('#schedule_standard')).toHaveClass(/active/);
    await expect(page.locator('#reservations table.reservations')).toHaveCount(7);
    await expect(page.locator('#reservations tr.slots')).toHaveCount(14);
    await expect(page.locator('#reservations table.reservations-tall')).toHaveCount(0);
    await expect(page.locator('#reservations table.condensed')).toHaveCount(0);
  });

  test('wide shows the whole week in one table', async ({ page }) => {
    await page.locator('#schedule_wide').click();

    await expect(page.locator('#schedule_wide')).toHaveClass(/active/);
    await expect(page.locator('#reservations table.reservations')).toHaveCount(1);
    await expect(page.locator('#reservations tr.slots')).toHaveCount(2);
  });

  test('tall shows one tall table per day', async ({ page }) => {
    await page.locator('#schedule_tall').click();

    await expect(page.locator('#schedule_tall')).toHaveClass(/active/);
    await expect(page.locator('#reservations table.reservations-tall')).toHaveCount(7);
  });

  test('week shows the condensed overview without bookable slots', async ({ page }) => {
    await page.locator('#schedule_week').click();

    await expect(page.locator('#schedule_week')).toHaveClass(/active/);
    await expect(page.locator('#reservations table.condensed')).toHaveCount(1);
    await expect(page.locator('#reservations td.reservable')).toHaveCount(0);
  });

  test('selecting a layout deselects the others', async ({ page }) => {
    await page.locator('#schedule_tall').click();

    await expect(page.locator('#schedule_tall')).toHaveClass(/active/);
    await expect(page.locator('#schedule_standard')).not.toHaveClass(/active/);
    await expect(page.locator('#schedule_wide')).not.toHaveClass(/active/);
    await expect(page.locator('#schedule_week')).not.toHaveClass(/active/);
  });
});

test.describe('mobile layout', () => {
  // defaultBrowserType cannot be set inside a describe block.
  const { defaultBrowserType, ...phone } = devices['Pixel 5'];
  test.use(phone);

  test('shows a single mobile table and only the layouts that suit a phone', async ({ page }) => {
    await page.goto(WEEK);
    await expect(page.locator('#page-schedule')).toBeVisible();

    await expect(page.locator('#reservations table.reservations.mobile')).toHaveCount(1);
    await expect(page.locator('#schedule_standard')).toBeVisible();
    await expect(page.locator('#schedule_tall')).toBeVisible();
    await expect(page.locator('#schedule_wide')).toBeHidden();
    await expect(page.locator('#schedule_week')).toBeHidden();
  });
});

test.describe('date picker', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(WEEK);
    await expect(page.locator('#page-schedule')).toBeVisible();
  });

  test('the calendar toggle opens and closes the picker', async ({ page }) => {
    const day = page.locator('.flatpickr-day[aria-label="March 20, 2030"]');
    await expect(page.locator('#datepicker')).not.toHaveClass(/show/);
    await expect(day).toBeHidden();

    await page.locator('#calendar_toggle').click();
    await expect(page.locator('#datepicker')).toHaveClass(/show/);
    await expect(day).toBeVisible();

    await page.locator('#calendar_toggle').click();
    await expect(page.locator('#datepicker')).not.toHaveClass(/show/);
    await expect(day).toBeHidden();
  });

  test('picking a day in another week moves the schedule to that week', async ({ page }) => {
    await expect(page.locator('.schedule-dates').first()).toContainText(WEEK_RANGE);

    await page.locator('#calendar_toggle').click();
    await page.locator('.flatpickr-day[aria-label="March 20, 2030"]').click();

    await expect(page).toHaveURL(/sd=2030-03-20/);
    await expect(page.locator('.schedule-dates').first()).toContainText('03/17/2030 - 03/23/2030');
  });

  test('picking a day in the shown week keeps the same week', async ({ page }) => {
    await page.locator('#calendar_toggle').click();
    await page.locator('.flatpickr-day[aria-label="March 6, 2030"]').click();

    await expect(page).toHaveURL(/sd=2030-03-06/);
    await expect(page.locator('.schedule-dates').first()).toContainText(WEEK_RANGE);
  });
});

test.describe('resource filter', () => {
  const rooms = [
    { id: '1', name: 'Conference Room 1', other: 'Conference Room 2' },
    { id: '2', name: 'Conference Room 2', other: 'Conference Room 1' },
  ];

  test.beforeEach(async ({ page }) => {
    await page.goto(WEEK);
    await expect(page.locator('#page-schedule')).toBeVisible();
  });

  async function filterBy(page: Page, id: string) {
    await page.locator('#resourceGroups .jqtree-toggler').first().click();
    await page.locator(`#resourceGroups input[resource-id="${id}"]`).check();
    await page.locator('#advancedFilter button[type="submit"]').click();
  }

  for (const room of rooms) {
    test(`filtering by ${room.name} hides the other room`, async ({ page }) => {
      await filterBy(page, room.id);

      const names = page.locator('#reservations a.resourceNameSelector');
      await expect(names.filter({ hasText: room.name }).first()).toBeVisible();
      await expect(names.filter({ hasText: room.other })).toHaveCount(0);
      await expect(page.locator('#reservations tr.slots')).toHaveCount(7);
    });
  }

  test('the selected room stays selected after a reload', async ({ page }) => {
    await filterBy(page, '2');
    await expect(page.locator('#reservations tr.slots')).toHaveCount(7);

    await page.reload();

    await expect(page.locator('#resourceGroups input[resource-id="2"]')).toBeChecked();
    await expect(page.locator('#resourceGroups input[resource-id="1"]')).not.toBeChecked();
    await expect(page.locator('#reservations tr.slots')).toHaveCount(7);
  });

  test('show all resources clears the filter', async ({ page }) => {
    await filterBy(page, '1');
    await expect(page.locator('#reservations tr.slots')).toHaveCount(7);

    await page.locator('#show_all_resources').click();

    await expect(page.locator('#reservations tr.slots')).toHaveCount(14);
    await expect(page.locator('#resourceGroups input:checked')).toHaveCount(0);
  });
});
