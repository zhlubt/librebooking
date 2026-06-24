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
        $this->presenter->PageLoad($user);
    }

    public function BindBooking(array $vm)
    {
        foreach ($vm as $key => $value) {
            $this->Set(ucfirst($key), $value);
        }
        $this->Set('Mode', 'form');
        $this->Set('HideNavBar', true);
        $this->Display('zhl-book.tpl');
    }

    public function BindSuccess(array $vm)
    {
        foreach ($vm as $key => $value) {
            $this->Set(ucfirst($key), $value);
        }
        $this->Set('Mode', 'success');
        $this->Set('HideNavBar', true);
        $this->Display('zhl-book.tpl');
    }

    public function RedirectToSuccess($referenceNumber)
    {
        $this->Redirect('zhl-book.php?booked=' . urlencode($referenceNumber));
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
    public function RedirectToSuccess($referenceNumber);
    public function RedirectToDashboard();
}
