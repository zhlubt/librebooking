<?php

require_once(ROOT_DIR . 'lib/Email/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlEmailLayout.php');

/**
 * ZHL B — Wunschtermin-Anfrage-Mail (Klartext, kein Smarty-Template).
 *
 * Generisch: trägt Empfänger (To), optional CC, Betreff und fertigen Klartext-Body. Wird benutzt für
 *  - die Benachrichtigung ans Medien-Team bei neuer Anfrage (To = medien_email, CC = Anfragender),
 *  - die Rückmeldung an den Anfragenden bei Buchung/Ablehnung durch den Admin (To = Nutzer).
 *
 * EmailMessage-Subklassen-Falle: KEINE Props mit Basisklassen-Namen — zhl-präfixiert (vgl.
 * ZhlHauspostEmail / ZhlMediaInfoEmail).
 */
class ZhlTerminRequestEmail extends EmailMessage
{
    private $zhlTo;
    private $zhlCc;
    private $zhlSubject;
    private $zhlBody;

    /**
     * @param EmailAddress[] $to
     * @param EmailAddress[] $cc
     */
    public function __construct(array $to, array $cc, string $subject, string $body, $language = null)
    {
        $this->zhlTo = $to;
        $this->zhlCc = $cc;
        $this->zhlSubject = $subject;
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
        return $this->zhlSubject;
    }

    public function Body()
    {
        return ZhlEmailLayout::WrapText([
            'title' => $this->zhlSubject,
            'text' => $this->zhlBody,
        ]);
    }
}
