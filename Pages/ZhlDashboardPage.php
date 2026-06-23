<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/ZhlDashboardPresenter.php');

/**
 * ZHL-Medienausleihe — neue verfügbarkeit-first Dashboard-Startseite (SPEC-UX-DASHBOARD v1).
 * SecurePage (Login erzwungen), pageDepth 0 (liegt direkt unter Web/). Read-only.
 */
class ZhlDashboardPage extends SecurePage implements IZhlDashboardPage
{
    /** @var ZhlDashboardPresenter */
    private $presenter;

    public function __construct()
    {
        parent::__construct('Medienausleihe');
        $this->presenter = new ZhlDashboardPresenter($this);
    }

    public function PageLoad()
    {
        $user = ServiceLocator::GetServer()->GetUserSession();
        $this->presenter->PageLoad($user);
        $this->Set('HideNavBar', true);
        $this->Display('zhl-dashboard.tpl');
    }

    public function BindDashboard(array $vm)
    {
        foreach ($vm as $key => $value) {
            $this->Set(ucfirst($key), $value);
        }
    }
}

interface IZhlDashboardPage
{
    public function BindDashboard(array $vm);
}
