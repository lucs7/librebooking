<?php

namespace LibreBooking\Presenters\Authentication;

use LibreBooking\Pages\Authentication\ILoginBasePage;
use ServiceLocator;
use Pages;

require_once(ROOT_DIR . 'Pages/Authentication/ILoginBasePage.php');

class LoginRedirector
{
    public static function Redirect(ILoginBasePage $page)
    {
        $redirect = $page->GetResumeUrl();

        if (!empty($redirect)) {
            $page->Redirect(html_entity_decode($redirect));
        } else {
            $defaultId = ServiceLocator::GetServer()->GetUserSession()->HomepageId;
            $url = Pages::UrlFromId($defaultId);
            $page->Redirect(empty($url) ? Pages::UrlFromId(Pages::DEFAULT_HOMEPAGE_ID) : $url);
        }
    }
}

class_alias(__NAMESPACE__.'\\LoginRedirector', 'LoginRedirector');
