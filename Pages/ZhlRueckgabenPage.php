<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/ZhlRueckgabenPresenter.php');

/**
 * ZHL „Rückgaben" — Admin-Agenda, was in den nächsten 7/14/30 Tagen zurückkommt (+ was
 * bereits überfällig ist). Ersetzt die alte Einzeltages-Ansicht durch dieselbe
 * reservierungszentrierte Agenda wie „Ausleihen" (siehe [[ZhlLoanOverview]]). Nur fürs
 * ZHL-Team. Datei bleibt `zhl-medienmanager.php` (bestehende Links/QR-Codes/Cross-Links
 * aus zhl-termine-admin.php / zhl-resource-return.php zeigen dorthin).
 */
class ZhlRueckgabenPage extends SecurePage implements IZhlRueckgabenPage
{
    /** @var ZhlRueckgabenPresenter */
    private $presenter;

    public function __construct()
    {
        parent::__construct('ZhlRueckgabenTitle');
        $this->presenter = new ZhlRueckgabenPresenter($this);
    }

    public function PageLoad()
    {
        $user = ServiceLocator::GetServer()->GetUserSession();
        if (!($user->IsAdmin || $user->IsResourceAdmin || $user->IsScheduleAdmin || $user->IsGroupAdmin)) {
            http_response_code(403);
            echo 'Nur für das ZHL-Team (Admin).';
            return;
        }

        $daysParam = (int)$this->GetQuerystring('days');
        $days = in_array($daysParam, [7, 14, 30], true) ? $daysParam : 14;

        $this->presenter->PageLoad($user, $days);
        $this->Set('HideNavBar', false);
        $this->Display('zhl-rueckgaben.tpl');
    }

    public function BindRueckgaben(array $vm)
    {
        foreach ($vm as $key => $value) {
            $this->Set(ucfirst($key), $value);
        }
    }
}
