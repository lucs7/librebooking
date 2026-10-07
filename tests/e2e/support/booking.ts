import { expect, type Page } from '@playwright/test';

export const HALF_HOUR = 1800;
export const HOUR = 3600;
export const DAY = 86400;

export type Scope = 'this' | 'future' | 'all';
export const SCOPE_BUTTON: Record<Scope, string> = {
  this: '.btnUpdateThisInstance',
  future: '.btnUpdateFutureInstances',
  all: '.btnUpdateAllInstances',
};

export type Booking = { ref: string; start: number; end: number; resource: number };

export async function createBooking(
  page: Page,
  options: {
    date: string;
    title: string;
    resourceId?: number;
    begin?: string;
    end?: string;
    repeatUntil?: string;
  }
) {
  await page.goto(`reservation.php?rid=${options.resourceId ?? 1}&sid=1&rd=${options.date}`);
  await page.locator('#reservationTitle').fill(options.title);
  if (options.begin) {
    await page.locator('#BeginPeriod').selectOption(options.begin);
  }
  if (options.end) {
    await page.locator('#EndPeriod').selectOption(options.end);
  }
  if (options.repeatUntil) {
    await page.locator('#repeatOptions').selectOption('weekly');
    await page.locator('#EndRepeat + input').click();
    await page.locator(`.flatpickr-calendar.open .flatpickr-day[aria-label="${options.repeatUntil}"]`).click();
  }
  await page.locator('.btnCreate').first().click();
}

export async function expectCreated(page: Page) {
  const box = page.locator('#reservation-created');
  await expect(box).toContainText('successfully created');
  // innerText still has raw template whitespace while the dialog animates in.
  const message = ((await box.textContent()) ?? '').replace(/\s+/g, ' ').trim();
  return { reference: message.match(/number is (\S+)/)?.[1] ?? '', message };
}

export async function weekBookings(page: Page, date: string): Promise<Booking[]> {
  const loaded = page.waitForResponse((response) => response.url().includes('dr=reservations'));
  await page.goto(`schedule.php?sd=${date}`);
  await loaded;
  await page.waitForLoadState('networkidle');
  return page.locator('div.event.reserved.mine').evaluateAll((events) =>
    events.map((event) => ({
      ref: event.getAttribute('data-resid') ?? '',
      start: Number(event.getAttribute('data-start')),
      end: Number(event.getAttribute('data-end')),
      resource: Number(event.getAttribute('data-resourceid')),
    }))
  );
}

export async function seriesWeeks(page: Page, dates: string[]): Promise<Booking[][]> {
  const weeks: Booking[][] = [];
  for (const date of dates) {
    weeks.push(await weekBookings(page, date));
  }
  return weeks;
}

export async function changeTime(page: Page, reference: string, begin: string, end: string, scope?: Scope) {
  await page.goto(`reservation.php?rn=${reference}`);
  await page.waitForLoadState('networkidle');
  await page.locator('#BeginPeriod').selectOption(begin);
  await page.locator('#EndPeriod').selectOption(end);
  if (scope) {
    await page.locator('.update.prompt').first().click();
    await page.locator(`#updateButtons.show ${SCOPE_BUTTON[scope]}`).click();
  } else {
    await page.locator('.btnEdit').first().click();
  }
  await expect(page.locator('#reservation-updated')).toContainText('successfully updated');
}

export async function cancelBooking(page: Page, reference: string, scope: Scope = 'all') {
  await page.goto(`reservation.php?rn=${reference}`);
  await page.waitForLoadState('networkidle');
  const isSeries = (await page.locator('.update.prompt').count()) > 0;
  await page.getByRole('button', { name: 'More' }).first().click();
  await page.locator('.dropdown-menu.show a.delete').click();

  const deleted = page.waitForResponse(
    (response) => response.url().includes('reservation_delete.php') && response.request().method() === 'POST'
  );
  if (isSeries) {
    await page.locator(`#updateButtons.show ${SCOPE_BUTTON[scope]}`).click();
  } else {
    await page.locator('.modal.show .confirmDelete').click();
  }
  expect((await deleted).ok()).toBe(true);
  await expect(page.locator('#deleted-message')).toBeVisible();
}
