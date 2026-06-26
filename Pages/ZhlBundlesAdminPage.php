<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/ZhlBundlesAdminPresenter.php');

/**
 * Admin-Pflege der Bundles (Dashboard v2c). SecurePage, nur für ZHL-Team (Admin-Rollen).
 * Schreibzugriff per POST + CSRF; danach Post-Redirect-Get gegen versehentliches Resubmit.
 */
class ZhlBundlesAdminPage extends SecurePage implements IZhlBundlesAdminPage
{
    private $presenter;

    public function __construct()
    {
        parent::__construct('Bundles verwalten');
        $this->presenter = new ZhlBundlesAdminPresenter($this);
    }

    public function PageLoad()
    {
        $u = ServiceLocator::GetServer()->GetUserSession();
        if (!($u->IsAdmin || $u->IsResourceAdmin || $u->IsScheduleAdmin || $u->IsGroupAdmin)) {
            $this->Redirect('zhl-dashboard.php');
            return;
        }

        if ($this->IsPost()) {
            $this->EnforceCSRFCheck();
            $msg = $this->presenter->HandlePost();
            $this->Redirect('zhl-bundles-admin.php?msg=' . urlencode($msg));
            return;
        }

        $this->presenter->Load();
        $this->Set('Message', $this->GetQuerystring('msg'));
        $this->Set('HideNavBar', false);
        $this->Display('zhl-bundles-admin.tpl');
    }

    public function SetBundles($bundles)
    {
        $this->Set('Bundles', $bundles);
    }

    public function SetKnownTypes($types)
    {
        $this->Set('KnownTypes', $types);
    }

    public function SetDifficulties($difficulties)
    {
        $this->Set('Difficulties', $difficulties);
    }

    public function SetEinweisungLevels($levels)
    {
        $this->Set('EinweisungLevels', $levels);
    }
}

interface IZhlBundlesAdminPage
{
    public function SetBundles($bundles);
    public function SetKnownTypes($types);
    public function SetDifficulties($difficulties);
    public function SetEinweisungLevels($levels);
}
