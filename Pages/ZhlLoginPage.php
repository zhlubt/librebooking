<?php
/**
 * ZHL: Login-Seite im ZHL-Studio-Look.
 *
 * Dockt upgrade-sicher an LibreBookings Login an: erbt die komplette
 * Authentifizierungs-Logik von LoginPage und tauscht nur das Template.
 * Keine Core-Datei wird verändert. Aufruf über Web/zhl-login.php.
 */

require_once(ROOT_DIR . 'Pages/LoginPage.php');
require_once(ROOT_DIR . 'Presenters/LoginPresenter.php');

class ZhlLoginPage extends LoginPage
{
    public function PageLoad()
    {
        // Presenter füllt alle Login-Variablen (Prompts, Register-/Reset-URL, Fehler).
        $this->presenter->PageLoad();
        $this->Display('zhl-login.tpl');
    }
}
