<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/ZhlBookPresenter.php');

/**
 * ZHL-Buchungs-Schritt (v-book). SecurePage, pageDepth 0. Stufe 1: nur Anzeige des Buchungs-Formulars
 * (Gerät, Zeitraum, Abholung/Einführung). Native Chrome ausgeblendet (eigene ZHL-Oberfläche).
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
        $this->presenter->PageLoad($user);
    }

    public function BindBooking(array $vm)
    {
        foreach ($vm as $key => $value) {
            $this->Set(ucfirst($key), $value);
        }
        $this->Set('HideNavBar', true);
        $this->Display('zhl-book.tpl');
    }

    public function RedirectToDashboard()
    {
        $this->Redirect('zhl-dashboard.php');
    }
}

interface IZhlBookPage
{
    public function BindBooking(array $vm);
    public function RedirectToDashboard();
}
