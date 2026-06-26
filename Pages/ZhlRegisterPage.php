<?php

require_once(ROOT_DIR . 'Pages/RegistrationPage.php');

/**
 * ZHL-Registrierung im Studio-Look (tpl/zhl-register.tpl). Erbt die komplette Registrierungs- und
 * Validierungslogik von RegistrationPage, reduziert das Formular aber auf E-Mail/Vorname/Nachname/
 * Passwort (Datensparsamkeit):
 *   - username = E-Mail (keine separate Benutzerkennung),
 *   - Zeitzone/Homepage = Defaults, Telefon/Position/Organisation leer,
 *   - Passwort-Bestätigung = Passwort (ein Feld).
 *
 * Erfolgreiche Registrierung läuft über den nativen Pfad (Validierung → Register → PostRegistration:
 * bei aktiver E-Mail-Aktivierung Redirect auf die Aktivierungs-Hinweisseite). Schlägt die native
 * Validierung fehl, rendert ActionPage von sich aus NICHTS → wir fangen das ab und zeigen das
 * ZHL-Formular erneut mit Hinweis.
 *
 * Bei einem LibreBooking-Upgrade die eine Zeile in Web/register.php erneut anwenden.
 */
class ZhlRegisterPage extends RegistrationPage
{
    public function ProcessPageLoad()
    {
        if (!Configuration::Instance()->GetKey(ConfigKeys::REGISTRATION_ALLOW_SELF, new BooleanConverter())) {
            $this->Redirect(Pages::LOGIN);
            return;
        }
        $this->renderForm('');
    }

    public function ProcessAction()
    {
        // Native Validierung + Registrierung. Erfolg → Redirect()+die() (s. u., echte Weiterleitung auf die
        // Aktivierungs-Hinweisseite). Kehrt der Aufruf zurück, ist die Validierung fehlgeschlagen → Formular
        // erneut zeigen.
        parent::ProcessAction();
        $this->renderForm('Die Registrierung war nicht möglich. Bitte prüfe: E-Mail nur @uni-bayreuth.de oder @myubt.de und noch nicht registriert, sowie ein ausreichend sicheres Passwort.');
    }

    /**
     * SYNCHRONER Flow statt der AJAX-Antwort der nativen RegistrationPage:
     * - Redirect() leitet ECHT weiter (RegistrationPage::Redirect würde nur JSON setzen). RedirectPage()
     *   ist die echte header()+die()-Weiterleitung. Greift bei Erfolg (PostRegistration → Aktivierungsseite)
     *   und beim Self-Registration-Bounce.
     */
    public function Redirect($url)
    {
        $this->RedirectPage($url);
    }

    /**
     * - IsValid() validiert NUR und gibt bool zurück, OHNE die native JSON-Fehlerantwort (ActionPage::IsValid
     *   würde bei Fehlschlag sofort json_data.tpl ausgeben → unverträglich mit unserem HTML-Re-Render).
     *   Schlägt die Validierung fehl, kehrt ProcessAction zurück und renderForm() zeigt das Formular mit Hinweis.
     */
    public function IsValid()
    {
        return $this->smarty->IsValid();
    }

    private function renderForm(string $flashError)
    {
        $post = $this->IsPostBack();
        $this->Set('FlashError', $flashError);
        $this->Set('Email', $post ? $this->GetEmail() : '');
        $this->Set('FirstName', $post ? $this->GetFirstName() : '');
        $this->Set('LastName', $post ? $this->GetLastName() : '');
        $this->Set('HideNavBar', true);
        $this->Display('zhl-register.tpl');
    }

    /** username = E-Mail. */
    public function GetLoginName()
    {
        return $this->GetEmail();
    }

    public function GetTimezone()
    {
        return Configuration::Instance()->GetDefaultTimezone();
    }

    public function GetHomepage()
    {
        return Configuration::Instance()->GetKey(ConfigKeys::DEFAULT_HOMEPAGE, new IntConverter());
    }

    public function GetPhone()
    {
        return '';
    }

    public function GetOrganization()
    {
        return '';
    }

    public function GetPosition()
    {
        return '';
    }

    /** Ein Passwort-Feld → Bestätigung = Passwort. */
    public function GetPasswordConfirm()
    {
        return $this->GetPassword();
    }
}
