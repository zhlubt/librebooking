<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/ZhlAusleihenPresenter.php');

/**
 * ZHL „Ausleihen" — Admin-Agenda, was in den nächsten 7/14/30 Tagen abgeholt wird.
 * Ersetzt die reine Übergabe-Terminliste durch eine reservierungszentrierte Sicht
 * (siehe [[ZhlLoanOverview]]). Nur fürs ZHL-Team.
 */
class ZhlAusleihenPage extends SecurePage implements IZhlAusleihenPage
{
    /** @var ZhlAusleihenPresenter */
    private $presenter;

    public function __construct()
    {
        parent::__construct('Ausleihen');
        $this->presenter = new ZhlAusleihenPresenter($this);
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
        $this->Display('zhl-ausleihen.tpl');
    }

    public function BindAusleihen(array $vm)
    {
        foreach ($vm as $key => $value) {
            $this->Set(ucfirst($key), $value);
        }
    }
}
