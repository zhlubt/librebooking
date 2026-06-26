<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/ZhlBundleBookPresenter.php');

/**
 * ZHL Bundle-Buchung (SPEC-BUNDLE-BOOKING). SecurePage — KEIN HideNavBar (zeigt die grüne ZHL-Chrome).
 * GET = Bundle-Formular (Kalender + Alternativen + After-Phase + Packliste). POST = ein/zwei native
 * Reservierungen über die ZhlReservationFacade (Multi-Resource). CSRF + Post-Redirect-Get.
 */
class ZhlBundleBookPage extends SecurePage implements IZhlBundleBookPage
{
    private $presenter;

    public function __construct()
    {
        parent::__construct('Bundle buchen');
        $this->presenter = new ZhlBundleBookPresenter($this);
    }

    public function PageLoad()
    {
        $user = ServiceLocator::GetServer()->GetUserSession();
        if ($this->IsPost()) {
            $this->EnforceCSRFCheck();
            $this->presenter->HandlePost($user);
            return;
        }
        // AJAX: Abhol-/Einführungstermine zum gewählten Aufnahme-Start nachladen (ohne Reload → Formularzustand bleibt).
        if (isset($_GET['ajax']) && $_GET['ajax'] === 'slots') {
            $this->presenter->AjaxSlots($user);
            return;
        }
        $this->presenter->PageLoad($user);
    }

    public function BindBundle(array $vm)
    {
        foreach ($vm as $key => $value) {
            $this->Set(ucfirst($key), $value);
        }
        $this->Set('Mode', 'form');
        $this->Set('HideNavBar', false);
        $this->Display('zhl-bundle-book.tpl');
    }

    public function BindSuccess(array $vm)
    {
        foreach ($vm as $key => $value) {
            $this->Set(ucfirst($key), $value);
        }
        $this->Set('Mode', 'success');
        $this->Set('HideNavBar', false);
        $this->Display('zhl-bundle-book.tpl');
    }

    public function RedirectToDashboard()
    {
        $this->Redirect('zhl-assistant.php');
    }

    public function RedirectToSuccess(string $query)
    {
        $this->Redirect('zhl-bundle-book.php?' . $query);
    }
}

interface IZhlBundleBookPage
{
    public function BindBundle(array $vm);
    public function BindSuccess(array $vm);
    public function RedirectToDashboard();
    public function RedirectToSuccess(string $query);
}
