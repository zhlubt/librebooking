<?php

require_once(ROOT_DIR . 'Pages/Authentication/ILoginBasePage.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Config/namespace.php');

class LoginRedirector
{
    public static function Redirect(ILoginBasePage $page)
    {
        $redirect = $page->GetResumeUrl();
        // ZHL: jeder Login landet auf dem ZHL-Dashboard (ID 1 zeigt darauf) — die individuell
        // gespeicherte HomepageId (z. B. „Zeitplan") wird bewusst ignoriert, damit niemand mehr auf
        // der alten LibreBooking-Optik (schedule.php) ankommt. Deep-Links (?redirect=) bleiben erhalten.
        $fallback = Pages::UrlFromId(Pages::DEFAULT_HOMEPAGE_ID);

        if (!empty($redirect)) {
            $page->Redirect(RedirectUrlSanitizer::Sanitize(
                url: $redirect,
                path: '',
                scriptUrl: Configuration::Instance()->GetScriptUrl(),
                fallback: $fallback
            ));
        } else {
            $page->Redirect($fallback);
        }
    }
}
