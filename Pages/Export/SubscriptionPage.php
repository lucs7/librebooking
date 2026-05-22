<?php

require_once(ROOT_DIR . 'Pages/Page.php');
require_once(ROOT_DIR . 'Pages/Export/ICalendarSubscriptionPage.php');
require_once(ROOT_DIR . 'Presenters/CalendarSubscriptionPresenter.php');
require_once(ROOT_DIR . 'lib/Application/Schedule/CalendarSubscriptionService.php');
require_once(ROOT_DIR . 'lib/Application/Schedule/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Authentication/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');

/**
 * Abstract base class for calendar subscription feed pages (ICS, Atom, etc.).
 *
 * Provides:
 *  - Shared presenter/service constructor wiring
 *  - A deferred $notFound flag with a unified SetIsNotFound() implementation
 *  - Optional HTTP Basic Authentication via tryBasicAuth()
 *
 * Subclasses must implement PageLoad() to render the specific feed format.
 */
abstract class SubscriptionPage extends Page implements ICalendarSubscriptionPage
{
    protected CalendarSubscriptionPresenter $presenter;

    /** @var iCalendarReservationView[] */
    protected array $reservations = [];

    protected bool $notFound = false;

    /**
     * Session built from successful Basic Auth credentials. Request-scoped only:
     * never persisted to $_SESSION, never emits a PHPSESSID cookie. Consumed by
     * the presenter as an override when synthesizing slot labels.
     */
    protected ?UserSession $feedUserSession = null;

    protected function __construct()
    {
        $authorization = new ReservationAuthorization(PluginManager::Instance()->LoadAuthorization());
        $service = new CalendarSubscriptionService(new UserRepository(), new ResourceRepository(), new ScheduleRepository());
        $validator = new CalendarSubscriptionValidator($this, $service);
        $this->presenter = new CalendarSubscriptionPresenter(
            $this,
            new ReservationViewRepository(),
            $validator,
            $service,
            new PrivacyFilter($authorization)
        );
        parent::__construct('', 1);
    }

    public function GetFeedUserSession(): ?UserSession
    {
        return $this->feedUserSession;
    }

    public function SetReservations($reservations): void
    {
        $this->reservations = $reservations;
    }

    /**
     * Signals that the request should be aborted. SetIsNotFound() may be called
     * by the presenter (invalid subscription key) or by tryBasicAuth() (bad
     * credentials). PageLoad() implementations must check $this->notFound after
     * each step and return early if it is true.
     */
    public function SetIsNotFound(): void
    {
        $this->notFound = true;
    }

    public function GetSubscriptionKey()
    {
        return $this->GetQuerystring(QueryStringKeys::SUBSCRIPTION_KEY);
    }

    public function GetUserId()
    {
        return $this->GetQuerystring(QueryStringKeys::USER_ID);
    }

    public function GetScheduleId()
    {
        return $this->GetQuerystring(QueryStringKeys::SCHEDULE_ID);
    }

    public function GetResourceId()
    {
        return $this->GetQuerystring(QueryStringKeys::RESOURCE_ID);
    }

    public function GetResourceGroupId()
    {
        return $this->GetQuerystring(QueryStringKeys::RESOURCE_GROUP_ID);
    }

    public function GetAccessoryIds()
    {
        return intval($this->GetQuerystring(QueryStringKeys::ACCESSORY_ID));
    }

    public function GetPastNumberOfDays()
    {
        return intval($this->GetQuerystring(QueryStringKeys::SUBSCRIPTION_DAYS_PAST));
    }

    public function GetFutureNumberOfDays()
    {
        return intval($this->GetQuerystring(QueryStringKeys::SUBSCRIPTION_DAYS_FUTURE));
    }

    /**
     * Gate the request and optionally perform HTTP Basic Authentication.
     *
     * Order of checks (fail-closed):
     *  1. If ICS is disabled, abort with notFound. Never reach password code.
     *  2. If no subscription key is supplied, abort with notFound. The
     *     endpoint must not act as a generic credential oracle for callers
     *     who don't know the feed secret.
     *  3. If basic.auth is disabled, return — the validator will still enforce
     *     icskey downstream and emit the feed for icskey-only clients.
     *  4. Validate Authorization header. On success, capture the UserSession
     *     on $this->feedUserSession (request-scoped only; the session is NOT
     *     written to $_SESSION and no PHPSESSID cookie is emitted).
     *  5. On failure or missing credentials, 401 + WWW-Authenticate and abort.
     */
    protected function tryBasicAuth(): void
    {
        if (!Configuration::Instance()->GetKey(ConfigKeys::ICS_ENABLED, new BooleanConverter())) {
            $this->notFound = true;
            return;
        }

        if (empty($this->GetSubscriptionKey())) {
            $this->notFound = true;
            return;
        }

        if (!Configuration::Instance()->GetKey(ConfigKeys::ICS_SUBSCRIPTION_BASIC_AUTH, new BooleanConverter())) {
            return;
        }

        $username = $_SERVER['PHP_AUTH_USER'] ?? null;
        $password = $_SERVER['PHP_AUTH_PW'] ?? null;

        if ($username === null || $password === null) {
            http_response_code(401);
            header('WWW-Authenticate: Basic realm="LibreBooking"');
            $this->notFound = true;
            return;
        }

        $authentication = $this->createWebAuthentication();
        $authentication->SetAuthSource('feed');

        if ($authentication->Validate($username, $password)) {
            $this->feedUserSession = $authentication->LoginForFeed($username);
        } else {
            http_response_code(401);
            header('WWW-Authenticate: Basic realm="LibreBooking"');
            $this->notFound = true;
        }
    }

    /**
     * Returns the IWebAuthentication implementation to use for Basic Auth.
     * Overridable in tests to inject a fake without touching the real plugin stack.
     */
    protected function createWebAuthentication(): IWebAuthentication
    {
        return new WebAuthentication(PluginManager::Instance()->LoadAuthentication());
    }
}
