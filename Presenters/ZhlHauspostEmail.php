<?php

require_once(ROOT_DIR . 'lib/Email/namespace.php');

/**
 * ZHL C2 — Hauspost-Versand-Benachrichtigung (Klartext, kein Smarty-Template).
 *
 * Wird beim Absenden einer Hauspost-Buchung versandt an Transport-Organisator + Medien-Team
 * (To) sowie den Ausleihenden (CC). Listet alle Formularfelder a–f2 + Buchungsnummer + Gerät
 * und den Hinweis, dass der Medienmanager den Antrag prüfen/freigeben muss.
 *
 * Achtung (EmailMessage-Subklassen-Falle): KEINE privaten Props mit Basisklassen-Namen
 * ($email/$name/...) deklarieren — sonst kollidiert es mit den protected Props der Basis.
 * Daher durchgängig zhl-präfixierte Eigenschaften.
 */
class ZhlHauspostEmail extends EmailMessage
{
    private $zhlTo;          // EmailAddress[]  (Transport + Medien)
    private $zhlCc;          // EmailAddress[]  (Ausleihender)
    private $zhlTitel;
    private $zhlBody;

    /**
     * @param EmailAddress[] $to
     * @param EmailAddress[] $cc
     * @param string $titel  Titel des Einsatzes (für den Betreff)
     * @param string $body   fertiger Klartext-Body
     */
    public function __construct(array $to, array $cc, string $titel, string $body, $language = null)
    {
        $this->zhlTo = $to;
        $this->zhlCc = $cc;
        $this->zhlTitel = $titel;
        $this->zhlBody = $body;
        parent::__construct($language);
    }

    public function To()
    {
        return $this->zhlTo;
    }

    public function CC()
    {
        return $this->zhlCc;
    }

    public function Subject()
    {
        return 'ZHL Medienausleihe — Hauspost-Versand: ' . $this->zhlTitel;
    }

    public function Body()
    {
        // Klartext; nl2br, damit auch HTML-Clients die Zeilenumbrüche zeigen.
        return nl2br(htmlspecialchars($this->zhlBody, ENT_QUOTES, 'UTF-8'));
    }
}
