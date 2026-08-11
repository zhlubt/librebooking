<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/ZhlBuchungAdminPresenter.php');

/**
 * ZHL „Buchungsakte" — Admin-Read-only-Sicht auf eine (auch fremde) Buchung (?ref=):
 * Eckdaten, Ausleihende:r, Geräte mit Einführungsstatus, Übergabetermine; für stornierte
 * Refs der Schnappschuss aus zhl_cancelled_booking. Nur fürs ZHL-Team; verlinkt aus den
 * „Vorhaben"-Titeln der Ausleihen-/Rückgaben-Listen und der Geräteakte. Die Owner-Sicht
 * (zhl-booking-detail.php) bleibt unverändert.
 */
class ZhlBuchungAdminPage extends SecurePage implements IZhlBuchungAdminPage
{
    /** @var ZhlBuchungAdminPresenter */
    private $presenter;

    public function __construct()
    {
        parent::__construct('ZhlBuchungAdminTitle');
        $this->presenter = new ZhlBuchungAdminPresenter($this);
    }

    public function PageLoad()
    {
        $user = ServiceLocator::GetServer()->GetUserSession();
        if (!($user->IsAdmin || $user->IsResourceAdmin || $user->IsScheduleAdmin || $user->IsGroupAdmin)) {
            http_response_code(403);
            echo 'Nur für das ZHL-Team (Admin).';
            return;
        }

        $ref = trim((string)$this->GetQuerystring('ref'));

        if ($this->IsPost()) {
            $this->EnforceCSRFCheck();
            $this->presenter->HandleEndLoan($user, $ref);
            return;
        }

        if ($ref === '' || !$this->presenter->PageLoad($user, $ref)) {
            http_response_code(404);
            echo 'Buchung nicht gefunden.';
            return;
        }

        $this->Set('EndedFlash', $this->GetQuerystring('beendet') === '1');
        $this->Set('EndedError', $this->GetQuerystring('beendet_fehler') === '1');
        $this->Set('HideNavBar', false);
        $this->Display('zhl-buchung-admin.tpl');
    }

    public function BindBuchung(array $vm)
    {
        foreach ($vm as $key => $value) {
            $this->Set(ucfirst($key), $value);
        }
    }

    public function RedirectAfterEndLoan(string $ref, string $status)
    {
        $q = 'zhl-buchung-admin.php?ref=' . urlencode($ref);
        $q .= $status === 'ok' ? '&beendet=1' : '&beendet_fehler=1';
        $this->Redirect($q);
    }
}
