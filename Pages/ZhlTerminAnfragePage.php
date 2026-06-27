<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/ZhlTerminAnfragePresenter.php');

/**
 * ZHL B — „Wunschtermin anfragen". Nutzer-Seite im grünen ZHL-Chrome: wenn für ein Gerät/Bundle kein
 * passender Termin buchbar ist, kann der Nutzer hier einen Wunsch-Zeitraum + Nachricht einreichen.
 * GET = Formular (oder Bestätigungs-Panel nach Redirect). POST = Anfrage anlegen (CSRF + PRG).
 */
class ZhlTerminAnfragePage extends SecurePage implements IZhlTerminAnfragePage
{
    /** @var ZhlTerminAnfragePresenter */
    private $presenter;

    public function __construct()
    {
        parent::__construct('Wunschtermin anfragen');
        $this->presenter = new ZhlTerminAnfragePresenter($this);
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
        $this->Set('HideNavBar', false);
        $this->Display('zhl-termin-anfrage.tpl');
    }

    public function Bind(array $vm)
    {
        foreach ($vm as $key => $value) {
            $this->Set(ucfirst($key), $value);
        }
    }

    public function GoTo($url)
    {
        $this->Redirect($url);
    }
}

interface IZhlTerminAnfragePage
{
    public function Bind(array $vm);
    public function GoTo($url);
}
