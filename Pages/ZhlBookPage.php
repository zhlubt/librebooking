<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/ZhlBookPresenter.php');

/**
 * ZHL-Buchungs-Schritt (v-book). SecurePage, pageDepth 0, native Chrome ausgeblendet.
 * GET = Formular (oder Erfolg). POST = echte Reservierung anlegen (CSRF + Post-Redirect-Get).
 */
class ZhlBookPage extends SecurePage implements IZhlBookPage
{
    private $presenter;

    public function __construct()
    {
        parent::__construct('Buchen');
        $this->presenter = new ZhlBookPresenter($this);
    }

    public function PageLoad()
    {
        $user = ServiceLocator::GetServer()->GetUserSession();
        if ($this->IsPost()) {
            $this->EnforceCSRFCheck();
            $this->presenter->HandlePost($user);
            return;
        }
        // AJAX: Abhol-/Einführungstermine zum gewählten Start nachladen (ohne Reload → Formularzustand bleibt).
        if (isset($_GET['ajax']) && $_GET['ajax'] === 'slots') {
            $this->presenter->AjaxSlots($user);
            return;
        }
        $this->presenter->PageLoad($user);
    }

    public function BindBooking(array $vm)
    {
        foreach ($vm as $key => $value) {
            $this->Set(ucfirst($key), $value);
        }
        $this->Set('Mode', 'form');
        $this->Set('HideNavBar', false);
        $this->Display('zhl-book.tpl');
    }

    public function BindSuccess(array $vm)
    {
        foreach ($vm as $key => $value) {
            $this->Set(ucfirst($key), $value);
        }
        $this->Set('Mode', 'success');
        $this->Set('HideNavBar', false);
        $this->Display('zhl-book.tpl');
    }

    public function RedirectToSuccess($referenceNumber, $warning = '')
    {
        $q = 'zhl-book.php?booked=' . urlencode($referenceNumber);
        if ($warning !== '') {
            $q .= '&warn=' . urlencode($warning);
        }
        $this->Redirect($q);
    }

    public function RedirectToDashboard()
    {
        $this->Redirect('zhl-dashboard.php');
    }
}

interface IZhlBookPage
{
    public function BindBooking(array $vm);
    public function BindSuccess(array $vm);
    public function RedirectToSuccess($referenceNumber, $warning = '');
    public function RedirectToDashboard();
}
