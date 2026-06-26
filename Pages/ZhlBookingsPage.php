<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/ZhlBookingsPresenter.php');

/**
 * ZHL „Meine Buchungen" — eigene Ausleihen des angemeldeten Nutzers, gruppiert
 * (laufend / anstehend / vergangen). SecurePage (Login erzwungen), read-only.
 * Rendert im grünen ZHL-Chrome (globalheader/globalfooter), HideNavBar bleibt false.
 */
class ZhlBookingsPage extends SecurePage implements IZhlBookingsPage
{
    /** @var ZhlBookingsPresenter */
    private $presenter;

    public function __construct()
    {
        parent::__construct('Meine Buchungen');
        $this->presenter = new ZhlBookingsPresenter($this);
    }

    public function PageLoad()
    {
        $user = ServiceLocator::GetServer()->GetUserSession();
        $this->presenter->PageLoad($user);
        $this->Set('HideNavBar', false);
        // Erfolgsbanner nach Storno (Redirect-Ziel der Detailseite).
        $this->Set('CancelledNotice', isset($_GET['storniert']) && $_GET['storniert'] === '1');
        $this->Set('CancelWarning', isset($_GET['warn']) ? trim((string)$_GET['warn']) : '');
        $this->Display('zhl-bookings.tpl');
    }

    public function BindBookings(array $vm)
    {
        foreach ($vm as $key => $value) {
            $this->Set(ucfirst($key), $value);
        }
    }
}

interface IZhlBookingsPage
{
    public function BindBookings(array $vm);
}
