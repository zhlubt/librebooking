<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/ZhlGeraetAdminPresenter.php');

/**
 * ZHL „Geräteakte" — Admin-Detailsicht eines Geräts (?rid=): Stammdaten, aktuelle
 * Ausleihe (inkl. „Ausleihe beenden"), Ausleih-Historie und Stornos. Nur fürs
 * ZHL-Team; verlinkt aus den Gerätenamen der Ausleihen-/Rückgaben-Listen.
 */
class ZhlGeraetAdminPage extends SecurePage implements IZhlGeraetAdminPage
{
    /** @var ZhlGeraetAdminPresenter */
    private $presenter;

    public function __construct()
    {
        parent::__construct('ZhlGeraetAdminTitle');
        $this->presenter = new ZhlGeraetAdminPresenter($this);
    }

    public function PageLoad()
    {
        $user = ServiceLocator::GetServer()->GetUserSession();
        if (!($user->IsAdmin || $user->IsResourceAdmin || $user->IsScheduleAdmin || $user->IsGroupAdmin)) {
            http_response_code(403);
            echo 'Nur für das ZHL-Team (Admin).';
            return;
        }

        $rid = (int)$this->GetQuerystring('rid');

        if ($this->IsPost()) {
            $this->EnforceCSRFCheck();
            $this->presenter->HandleEndLoan($user, $rid);
            return;
        }

        if ($rid <= 0 || !$this->presenter->PageLoad($user, $rid)) {
            http_response_code(404);
            echo 'Gerät nicht gefunden.';
            return;
        }

        $this->Set('EndedFlash', $this->GetQuerystring('beendet') === '1');
        $this->Set('EndedError', $this->GetQuerystring('beendet_fehler') === '1');
        $this->Set('HideNavBar', false);
        $this->Display('zhl-geraet-admin.tpl');
    }

    public function BindGeraet(array $vm)
    {
        foreach ($vm as $key => $value) {
            $this->Set(ucfirst($key), $value);
        }
    }

    public function RedirectAfterEndLoan(int $rid, string $status)
    {
        $q = 'zhl-geraet-admin.php?rid=' . $rid;
        $q .= $status === 'ok' ? '&beendet=1' : '&beendet_fehler=1';
        $this->Redirect($q);
    }
}
