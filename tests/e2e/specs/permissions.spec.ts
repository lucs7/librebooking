import { test, expect } from '../support/fixtures';
import { cancelBooking, createBooking, expectCreated, weekBookings } from '../support/booking';

// e2e.user: direct permission for Conference Room 1. e2e.group: Conference
// Room 2 through its group. Each spec books on its own far-future date.
const NO_PERMISSION = 'You do not have permission to access one or more of the requested resources';
const NOT_ALLOWED = 'You are not allowed to change this reservation';

test.describe('own bookings', () => {
  test('a user can create, change and cancel their own booking', async ({ userPage: user }) => {
    const date = '2030-10-15';
    await createBooking(user, { date, title: 'E2E user booking' });
    const { reference } = await expectCreated(user);

    await user.goto(`reservation.php?rn=${reference}`);
    await expect(user.locator('#reservationTitle')).toBeEditable();
    await user.locator('#reservationTitle').fill('E2E user booking (edited)');
    await user.locator('.btnEdit').first().click();
    await expect(user.locator('#reservation-updated')).toContainText('successfully updated');

    await cancelBooking(user, reference);
    expect(await weekBookings(user, date)).toEqual([]);
  });
});

test.describe("someone else's bookings", () => {
  test('a user can only view a booking of another user', async ({ page: admin, userPage: user }) => {
    const date = '2030-10-22';
    await createBooking(admin, { date, title: 'E2E admin booking' });
    const { reference } = await expectCreated(admin);

    await user.goto(`reservation.php?rn=${reference}`);
    await expect(user.locator('body')).toContainText('E2E admin booking');
    await expect(user.locator('#reservationTitle')).toHaveCount(0);
    await expect(user.locator('.btnEdit, .update.prompt')).toHaveCount(0);
    await expect(user.getByRole('button', { name: 'More' })).toHaveCount(0);
    await expect(user.getByRole('button', { name: 'Close' })).toBeVisible();

    const loaded = user.waitForResponse((response) => response.url().includes('dr=reservations'));
    await user.goto(`schedule.php?sd=${date}`);
    await loaded;
    await user.waitForLoadState('networkidle');
    const event = user.locator(`div.event[data-resid="${reference}"]`);
    await expect(event).toHaveCount(1);
    await expect(event).not.toHaveClass(/mine/);
    await expect(event).not.toHaveAttribute('draggable', 'true');

    await cancelBooking(admin, reference);
  });

  test('a direct request to change or delete it is refused', async ({ page: admin, userPage: user }) => {
    const date = '2030-10-29';
    await createBooking(admin, { date, title: 'E2E protected' });
    const { reference } = await expectCreated(admin);

    // A valid token, so the refusal comes from the permission check.
    await user.goto(`reservation.php?rid=1&sid=1&rd=${date}`);
    const token = await user.locator('input[name="CSRF_TOKEN"]').first().inputValue();

    for (const endpoint of ['reservation_update.php', 'reservation_delete.php']) {
      const response = await user.request.post(`ajax/${endpoint}`, {
        form: {
          CSRF_TOKEN: token,
          referenceNumber: reference,
          reservationTitle: 'E2E hijacked',
          beginDate: date,
          endDate: date,
          beginPeriod: '08:00:00',
          endPeriod: '08:30:00',
          resourceId: '1',
          scheduleId: '1',
          userId: '1',
          seriesUpdateScope: 'full',
        },
      });
      expect(await response.text()).toContain(NOT_ALLOWED);
    }

    await admin.goto(`reservation.php?rn=${reference}`);
    await expect(admin.locator('#reservationTitle')).toHaveValue('E2E protected');
    await cancelBooking(admin, reference);
  });

  test("the application admin can change and cancel a user's booking", async ({ page: admin, userPage: user }) => {
    const date = '2030-11-05';
    await createBooking(user, { date, title: 'E2E from the user' });
    const { reference } = await expectCreated(user);

    await admin.goto(`reservation.php?rn=${reference}`);
    await expect(admin.locator('#reservationTitle')).toBeEditable();
    await admin.locator('#reservationTitle').fill('E2E changed by the admin');
    await admin.locator('.btnEdit').first().click();
    await expect(admin.locator('#reservation-updated')).toContainText('successfully updated');

    await user.goto(`reservation.php?rn=${reference}`);
    await expect(user.locator('#reservationTitle')).toHaveValue('E2E changed by the admin');

    await cancelBooking(admin, reference);
    expect(await weekBookings(user, date)).toEqual([]);
  });
});

test.describe('direct resource permission', () => {
  test('a user sees every resource but can only book the ones with permission', async ({ userPage: user }) => {
    const date = '2030-11-12';
    await user.goto(`schedule.php?sd=${date}`);
    await expect(user.locator('#page-schedule')).toBeVisible();
    const bookable = user.locator('#reservations a.resourceNameSelector');
    const notBookable = user.locator('#reservations span.resourceNameSelector');
    await expect(bookable.filter({ hasText: 'Conference Room 1' }).first()).toBeVisible();
    await expect(bookable.filter({ hasText: 'Conference Room 2' })).toHaveCount(0);
    await expect(notBookable.filter({ hasText: 'Conference Room 2' }).first()).toBeVisible();

    await user.goto('schedule.php?sid=2');
    await expect(
      user.locator('#reservations span.resourceNameSelector', { hasText: 'E2E Restricted Room' }).first()
    ).toBeVisible();
    await expect(user.locator('#reservations a.resourceNameSelector')).toHaveCount(0);
  });

  test('a booking is only accepted for a resource the user has permission for', async ({ userPage: user }) => {
    const date = '2030-11-19';
    await createBooking(user, { date, title: 'E2E allowed room' });
    const { reference } = await expectCreated(user);
    await cancelBooking(user, reference);

    await createBooking(user, { date, title: 'E2E other room', resourceId: 2 });
    await expect(user.locator('#reservation-failed')).toContainText(NO_PERMISSION);

    await createBooking(user, {
      date,
      title: 'E2E restricted room',
      resourceId: 3,
      scheduleId: 2,
    });
    await expect(user.locator('#reservation-failed')).toContainText(NO_PERMISSION);
  });

  test('the application admin can book the restricted room', async ({ page: admin }) => {
    await createBooking(admin, {
      date: '2030-11-26',
      title: 'E2E admin restricted',
      resourceId: 3,
      scheduleId: 2,
    });
    const { reference } = await expectCreated(admin);

    await cancelBooking(admin, reference);
  });
});

test.describe('group resource permission', () => {
  test('a group member sees every resource but can only book the ones the group allows', async ({
    groupUserPage: user,
  }) => {
    await user.goto('schedule.php?sd=2030-12-03');
    await expect(user.locator('#page-schedule')).toBeVisible();
    const bookable = user.locator('#reservations a.resourceNameSelector');
    const notBookable = user.locator('#reservations span.resourceNameSelector');
    await expect(bookable.filter({ hasText: 'Conference Room 2' }).first()).toBeVisible();
    await expect(bookable.filter({ hasText: 'Conference Room 1' })).toHaveCount(0);
    await expect(notBookable.filter({ hasText: 'Conference Room 1' }).first()).toBeVisible();

    await user.goto('schedule.php?sid=2');
    await expect(user.locator('#reservations a.resourceNameSelector')).toHaveCount(0);
  });

  test('a booking is only accepted for a resource the group allows', async ({ groupUserPage: user }) => {
    const date = '2030-12-10';
    await createBooking(user, { date, title: 'E2E group room', resourceId: 2 });
    const { reference } = await expectCreated(user);
    await cancelBooking(user, reference);

    await createBooking(user, { date, title: 'E2E direct room', resourceId: 1 });
    await expect(user.locator('#reservation-failed')).toContainText(NO_PERMISSION);

    await createBooking(user, {
      date,
      title: 'E2E restricted room',
      resourceId: 3,
      scheduleId: 2,
    });
    await expect(user.locator('#reservation-failed')).toContainText(NO_PERMISSION);
  });

  test('each user only gets their own resource, at the same time', async ({
    userPage: direct,
    groupUserPage: grouped,
  }) => {
    const date = '2030-12-17';
    await createBooking(direct, { date, title: 'E2E direct user', resourceId: 1 });
    const first = await expectCreated(direct);
    await createBooking(grouped, { date, title: 'E2E group user', resourceId: 2 });
    const second = await expectCreated(grouped);

    expect((await weekBookings(direct, date)).map((booking) => booking.ref)).toEqual([first.reference]);
    expect((await weekBookings(grouped, date)).map((booking) => booking.ref)).toEqual([second.reference]);

    await cancelBooking(direct, first.reference);
    await cancelBooking(grouped, second.reference);
  });
});
