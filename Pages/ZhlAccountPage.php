<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/ZhlAccountPresenter.php');

/**
 * ZHL „Konto" — read-only Übersicht der eigenen Profildaten des angemeldeten
 * Nutzers im grünen ZHL-Chrome. Passwort- und Profil-Bearbeitung verlinken auf
 * die nativen LibreBooking-Seiten (profile.php / password.php), die nun ebenfalls
 * das grüne Chrome tragen. Keine auth-sensiblen Schreibzugriffe hier.
 */
class ZhlAccountPage extends SecurePage implements IZhlAccountPage
{
    /** @var ZhlAccountPresenter */
    private $presenter;

    public function __construct()
    {
        parent::__construct('Konto');
        $this->presenter = new ZhlAccountPresenter($this);
    }

    public function PageLoad()
    {
        $user = ServiceLocator::GetServer()->GetUserSession();
        $this->presenter->PageLoad($user);
        $this->Set('HideNavBar', false);
        $this->Display('zhl-account.tpl');
    }

    public function BindAccount(array $vm)
    {
        foreach ($vm as $key => $value) {
            $this->Set(ucfirst($key), $value);
        }
    }
}

interface IZhlAccountPage
{
    public function BindAccount(array $vm);
}
