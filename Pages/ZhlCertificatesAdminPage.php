<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/ZhlCertificatesAdminPresenter.php');

/**
 * Admin-Pflege der benannten Einführungs-Zertifikate (v-cert). SecurePage, nur ZHL-Team (Admin-Rollen).
 * POST → CSRF → HandlePost → Post-Redirect-Get.
 */
class ZhlCertificatesAdminPage extends SecurePage implements IZhlCertificatesAdminPage
{
    private $presenter;

    public function __construct()
    {
        parent::__construct('Zertifikate verwalten');
        $this->presenter = new ZhlCertificatesAdminPresenter($this);
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
            $this->Redirect('zhl-certificates-admin.php?msg=' . urlencode($msg));
            return;
        }

        $this->presenter->Load();
        $this->Set('Message', $this->GetQuerystring('msg'));
        $this->Set('HideNavBar', false);
        $this->Display('zhl-certificates-admin.tpl');
    }

    public function SetTypes($types)
    {
        $this->Set('Types', $types);
    }

    public function SetResources($resources)
    {
        $this->Set('AllResources', $resources);
    }
}

interface IZhlCertificatesAdminPage
{
    public function SetTypes($types);
    public function SetResources($resources);
}
