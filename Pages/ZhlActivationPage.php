<?php

require_once(ROOT_DIR . 'Pages/ActivationPage.php');
require_once(ROOT_DIR . 'Pages/Pages.php');

/**
 * ZHL-Aktivierung: fängt das "Microsoft/Outlook Safe Links"-Problem ab.
 *
 * Mailsysteme (Exchange, Defender, Virenscanner) rufen Links in E-Mails häufig
 * automatisch VORAB ab, um sie auf Schädlichkeit zu prüfen. Der Aktivierungslink ist
 * ein Einmal-GET-Link: dieser Vorab-Abruf aktiviert das Konto bereits und verbraucht
 * den Token. Klickt der Mensch danach selbst, ist der Token weg → die native Seite
 * zeigt "Wir können Ihr Benutzerkonto nicht aktivieren."
 *
 * Da die eigentliche Aktivierung in diesem Fall längst erfolgreich war (Konto aktiv),
 * leiten wir den menschlichen Klick freundlich zum Login um, statt einen Fehler zu
 * zeigen. Der Erfolgspfad (erster Treffer aktiviert + loggt ein + Redirect auf die
 * Startseite) bleibt unverändert.
 *
 * Upgrade-sicher: erbt die komplette Aktivierungslogik von ActivationPage, überschreibt
 * nur die Fehlerausgabe. Bei einem LibreBooking-Upgrade die eine Zeile in
 * Web/activate.php erneut anwenden.
 */
class ZhlActivationPage extends ActivationPage
{
    public function ShowError()
    {
        // Token nicht (mehr) gefunden: i. d. R. bereits aktiviert (Vorab-Abruf/Doppelklick)
        // oder abgelaufen. In beiden Fällen ist Einloggen der richtige nächste Schritt.
        $this->Redirect(Pages::LOGIN . '?reaktiviert=1');
    }
}
