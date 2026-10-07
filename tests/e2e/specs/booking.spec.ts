import { test, expect } from '@playwright/test';
import {
  cancelBooking,
  changeTime,
  createBooking,
  expectCreated,
  seriesWeeks,
  weekBookings,
  DAY,
  HALF_HOUR,
  HOUR,
  type Booking,
} from '../support/booking';

// Each spec books on its own far-future Tuesday and cancels what it created.
const DATE = '2030-03-12';
const DAY_HEADING = 'Tuesday, 3/12/30';
const TITLE = 'E2E booking life cycle';

test('a booking can be created, found, changed and deleted', async ({ page }) => {
  const dayTable = page.locator('table.reservations', { hasText: DAY_HEADING });
  const bookings = dayTable.locator('div.event.reserved.mine');
  let reference = '';

  await test.step('open the booking form from the schedule', async () => {
    await page.goto(`schedule.php?sd=${DATE}`);
    await dayTable.locator('a.resourceNameSelector', { hasText: 'Conference Room 1' }).click();

    await expect(page).toHaveURL(/reservation\.php\?.*rid=1.*rd=2030-03-12/);
    await expect(page.locator('#reservationTitle')).toBeVisible();
  });

  await test.step('create the booking', async () => {
    await page.locator('#reservationTitle').fill(TITLE);
    await page.locator('.btnCreate').first().click();

    const created = page.locator('#reservation-created');
    await expect(created).toContainText('successfully created');
    reference = ((await created.innerText()).match(/number is\s+(\S+)/) ?? [])[1] ?? '';
    expect(reference).toMatch(/^[0-9a-f]{16,}$/);
  });

  await test.step('see the booking on the schedule', async () => {
    await page.goto(`schedule.php?sd=${DATE}`);

    await expect(bookings).toHaveCount(1);
    await expect(bookings).toContainText('Admin Admin');
  });

  await test.step('change the title', async () => {
    await page.goto(`reservation.php?rn=${reference}`);
    await expect(page.locator('#reservationTitle')).toHaveValue(TITLE);

    await page.locator('#reservationTitle').fill(`${TITLE} (edited)`);
    await page.locator('.btnEdit').first().click();

    await expect(page.locator('#reservation-updated')).toContainText('successfully updated');
  });

  await test.step('see the saved change', async () => {
    await page.goto(`reservation.php?rn=${reference}`);

    await expect(page.locator('#reservationTitle')).toHaveValue(`${TITLE} (edited)`);
  });

  await test.step('delete the booking', async () => {
    await page.getByRole('button', { name: 'More' }).first().click();
    await page.locator('.dropdown-menu.show a.delete').click();

    const deleted = page.waitForResponse(
      (r) => r.url().includes('reservation_delete.php') && r.request().method() === 'POST'
    );
    await page.locator('.modal.show .confirmDelete').click();
    expect((await deleted).ok()).toBe(true);
  });

  await test.step('the booking is gone from the schedule', async () => {
    const loaded = page.waitForResponse((r) => r.url().includes('dr=reservations'));
    await page.goto(`schedule.php?sd=${DATE}`);
    await loaded;

    await expect(dayTable).toBeVisible();
    await expect(bookings).toHaveCount(0);
  });
});

test('changing the date and time moves the booking', async ({ page }) => {
  const day = (heading: string) => page.locator('table.reservations', { hasText: heading });
  let reference = '';
  let original: Booking;

  await test.step('create a booking on Tuesday 8:00 to 8:30', async () => {
    await createBooking(page, { date: '2030-06-18', title: 'E2E date and time' });
    reference = (await expectCreated(page)).reference;

    [original] = await weekBookings(page, '2030-06-18');
    expect(original.ref).toBe(reference);
    expect(original.end - original.start).toBe(HALF_HOUR);
  });

  await test.step('move it to Wednesday 9:00 to 9:30', async () => {
    await page.goto(`reservation.php?rn=${reference}`);
    await page.locator('#BeginDate + input').click();
    await page.locator('.flatpickr-calendar.open .flatpickr-day[aria-label="June 19, 2030"]').click();
    await page.locator('#BeginPeriod').selectOption('09:00:00');
    await page.locator('#EndPeriod').selectOption('09:30:00');
    await page.locator('.btnEdit').first().click();

    await expect(page.locator('#reservation-updated')).toContainText('successfully updated');
  });

  await test.step('the schedule shows the new day and time', async () => {
    const [moved] = await weekBookings(page, '2030-06-18');

    expect(moved.ref).toBe(reference);
    expect(moved.start - original.start).toBe(DAY + HOUR);
    expect(moved.end - moved.start).toBe(HALF_HOUR);
    await expect(day('Wednesday, 6/19/30').locator(`div.event[data-resid="${reference}"]`)).toHaveCount(1);
    await expect(day('Tuesday, 6/18/30').locator(`div.event[data-resid="${reference}"]`)).toHaveCount(0);
  });

  await test.step('the edit page shows the saved date and time', async () => {
    await page.goto(`reservation.php?rn=${reference}`);

    await expect(page.locator('#BeginDate')).toHaveValue('2030-06-19');
    await expect(page.locator('#EndDate')).toHaveValue('2030-06-19');
    await expect(page.locator('#BeginPeriod')).toHaveValue('09:00:00');
    await expect(page.locator('#EndPeriod')).toHaveValue('09:30:00');
  });

  await test.step('cancel the booking', async () => {
    await cancelBooking(page, reference);

    expect(await weekBookings(page, '2030-06-18')).toEqual([]);
  });
});

test.describe('overlapping bookings', () => {
  test('a booking that overlaps an existing one is rejected', async ({ page }) => {
    const date = '2030-03-19';
    await createBooking(page, { date, title: 'E2E first', begin: '08:00:00', end: '09:00:00' });
    const { reference } = await expectCreated(page);

    await createBooking(page, { date, title: 'E2E overlap', begin: '08:30:00', end: '09:30:00' });

    const failed = page.locator('#reservation-failed');
    await expect(failed).toContainText('could not be made');
    await expect(failed).toContainText('conflicting reservations');
    await expect(failed).toContainText('03/19/2030 - Conference Room 1');
    await expect(page.locator('#reservation-created')).toHaveCount(0);
    await expect(page.locator('#reservationTitle')).toBeVisible();

    const bookings = await weekBookings(page, date);
    expect(bookings.map((booking) => booking.ref)).toEqual([reference]);

    await cancelBooking(page, reference);
  });

  test('back to back bookings and the other resource do not conflict', async ({ page }) => {
    const date = '2030-03-26';
    await createBooking(page, { date, title: 'E2E morning', begin: '08:00:00', end: '09:00:00' });
    const first = (await expectCreated(page)).reference;
    await createBooking(page, { date, title: 'E2E next', begin: '09:00:00', end: '09:30:00' });
    const next = (await expectCreated(page)).reference;
    await createBooking(page, {
      date,
      title: 'E2E other room',
      resourceId: 2,
      begin: '08:00:00',
      end: '09:00:00',
    });
    const other = (await expectCreated(page)).reference;

    const bookings = await weekBookings(page, date);
    expect(bookings.map((booking) => [booking.ref, booking.resource]).sort()).toEqual(
      [
        [first, 1],
        [next, 1],
        [other, 2],
      ].sort()
    );

    for (const reference of [first, next, other]) {
      await cancelBooking(page, reference);
    }
    expect(await weekBookings(page, date)).toEqual([]);
  });

  test('cancelling a booking frees its slot', async ({ page }) => {
    const date = '2030-04-02';
    await createBooking(page, { date, title: 'E2E to cancel' });
    const { reference } = await expectCreated(page);

    await createBooking(page, { date, title: 'E2E blocked' });
    await expect(page.locator('#reservation-failed')).toContainText('conflicting reservations');

    await cancelBooking(page, reference);
    await createBooking(page, { date, title: 'E2E rebooked' });
    const rebooked = await expectCreated(page);

    expect(rebooked.reference).not.toBe(reference);
    await cancelBooking(page, rebooked.reference);
  });
});

test.describe('recurring bookings', () => {
  test('a weekly series books one reservation per week', async ({ page }) => {
    const weeks = ['2030-04-09', '2030-04-16', '2030-04-23'];
    await createBooking(page, { date: weeks[0], title: 'E2E weekly', repeatUntil: 'April 23, 2030' });

    const { reference, message } = await expectCreated(page);
    expect(message).toContain('04/09/2030, 04/16/2030, 04/23/2030');

    const booked = await seriesWeeks(page, [...weeks, '2030-04-30']);
    expect(booked.map((week) => week.length)).toEqual([1, 1, 1, 0]);
    expect(booked[0][0].ref).toBe(reference);
    expect(new Set(booked.slice(0, 3).map((week) => week[0].ref)).size).toBe(3);

    await cancelBooking(page, reference, 'all');
    expect((await seriesWeeks(page, weeks)).map((week) => week.length)).toEqual([0, 0, 0]);
  });

  const scopes = [
    {
      scope: 'this',
      label: 'only this instance',
      weeks: ['2030-05-07', '2030-05-14', '2030-05-21'],
      until: 'May 21, 2030',
      shifted: [0, HOUR, 0],
      remaining: [1, 0, 1],
    },
    {
      scope: 'future',
      label: 'this and future instances',
      weeks: ['2030-07-02', '2030-07-09', '2030-07-16'],
      until: 'July 16, 2030',
      shifted: [0, HOUR, HOUR],
      remaining: [1, 0, 0],
    },
    {
      scope: 'all',
      label: 'all instances',
      weeks: ['2030-08-06', '2030-08-13', '2030-08-20'],
      until: 'August 20, 2030',
      shifted: [HOUR, HOUR, HOUR],
      remaining: [0, 0, 0],
    },
  ] as const;

  for (const { scope, label, weeks, until, shifted, remaining } of scopes) {
    test(`a series can be changed and cancelled for ${label}`, async ({ page }) => {
      await createBooking(page, { date: weeks[0], title: `E2E series ${scope}`, repeatUntil: until });
      await expectCreated(page);
      const before = (await seriesWeeks(page, [...weeks])).map((week) => week[0]);
      expect(before).toHaveLength(3);
      const middle = before[1];

      await changeTime(page, middle.ref, '09:00:00', '09:30:00', scope);
      const after = (await seriesWeeks(page, [...weeks])).map((week) => week[0]);
      expect(after.map((booking, index) => booking.start - before[index].start)).toEqual([...shifted]);
      expect(after.map((booking) => booking.end - booking.start)).toEqual([HALF_HOUR, HALF_HOUR, HALF_HOUR]);

      await cancelBooking(page, middle.ref, scope);
      const left = await seriesWeeks(page, [...weeks]);
      expect(left.map((week) => week.length)).toEqual([...remaining]);

      const survivor = left.flat()[0];
      if (survivor) {
        await cancelBooking(page, survivor.ref, 'all');
      }
      expect((await seriesWeeks(page, [...weeks])).map((week) => week.length)).toEqual([0, 0, 0]);
    });
  }

  test('a series that hits an existing booking is rejected', async ({ page }) => {
    const weeks = ['2030-09-03', '2030-09-10', '2030-09-17'];
    await createBooking(page, { date: weeks[1], title: 'E2E in the way' });
    const { reference } = await expectCreated(page);

    await createBooking(page, { date: weeks[0], title: 'E2E blocked series', repeatUntil: 'September 17, 2030' });

    const failed = page.locator('#reservation-failed');
    await expect(failed).toContainText('conflicting reservations');
    await expect(failed).toContainText('09/10/2030');
    const booked = await seriesWeeks(page, weeks);
    expect(booked.map((week) => week.map((booking) => booking.ref))).toEqual([[], [reference], []]);

    await cancelBooking(page, reference);
  });
});
