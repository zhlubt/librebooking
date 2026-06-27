<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/ZhlBookingsPresenter.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlTerminRequest.php');

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

        // B: eigene offene Wunschtermin-Anfrage zurückziehen (PRG). SetStatus gated auf
        // status=open AND user_id = eigener (sicheres Storno fremder/erledigter Anfragen ausgeschlossen).
        if ($this->IsPost() && $this->GetForm('action') === 'cancel_request') {
            $this->EnforceCSRFCheck();
            $reqId = (int)$this->GetForm('req_id');
            if ($reqId > 0) {
                ZhlTerminRequest::SetStatus(
                    ServiceLocator::GetDatabase(),
                    $reqId,
                    'cancelled',
                    (int)$user->UserId,
                    'Vom Nutzer zurückgezogen.',
                    null,
                    (int)$user->UserId
                );
            }
            $this->Redirect('zhl-bookings.php?anfrage_storniert=1');
            return;
        }

        $this->presenter->PageLoad($user);
        $this->Set('HideNavBar', false);
        // Erfolgsbanner nach Storno (Redirect-Ziel der Detailseite).
        $this->Set('CancelledNotice', isset($_GET['storniert']) && $_GET['storniert'] === '1');
        $this->Set('CancelWarning', isset($_GET['warn']) ? trim((string)$_GET['warn']) : '');
        $this->Set('RequestCancelledNotice', isset($_GET['anfrage_storniert']) && $_GET['anfrage_storniert'] === '1');
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
