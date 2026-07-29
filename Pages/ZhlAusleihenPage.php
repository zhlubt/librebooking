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
        parent::__construct('ZhlAusleihenTitle');
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

        if ($this->IsPost()) {
            $this->EnforceCSRFCheck();
            $this->presenter->HandleEndLoan($user);
            return;
        }

        $daysParam = (int)$this->GetQuerystring('days');
        $days = in_array($daysParam, [7, 14, 30], true) ? $daysParam : 14;

        $filterParam = (string)($this->GetQuerystring('filter') ?? 'upcoming');
        $filter = in_array($filterParam, ['upcoming', 'this_week', 'active'], true) ? $filterParam : 'upcoming';

        $this->presenter->PageLoad($user, $days, $filter);
        $this->Set('EndedFlash', $this->GetQuerystring('beendet') === '1');
        $this->Set('EndedError', $this->GetQuerystring('beendet_fehler') === '1');
        $this->Set('HideNavBar', false);
        $this->Display('zhl-ausleihen.tpl');
    }

    public function BindAusleihen(array $vm)
    {
        foreach ($vm as $key => $value) {
            $this->Set(ucfirst($key), $value);
        }
    }

    public function RedirectAfterEndLoan(string $status)
    {
        $q = 'zhl-medienmanager-ausleihen.php?filter=active';
        $q .= $status === 'ok' ? '&beendet=1' : '&beendet_fehler=1';
        $this->Redirect($q);
    }
}
