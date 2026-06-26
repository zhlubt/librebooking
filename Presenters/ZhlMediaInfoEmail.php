<?php

require_once(ROOT_DIR . 'lib/Email/namespace.php');

/**
 * ZHL D2 — Bestätigungs-Begleitmail mit Info-Material zu den gebuchten Medien (Klartext).
 *
 * Geht nach erfolgreicher Buchung an den Ausleihenden (To) und listet je gebuchtem Gerät den
 * Info-Link des Geräte-Typs („Geräte-Name → Info-Material"). Wird nur versandt, wenn überhaupt
 * ein Info-Link vorhanden ist.
 *
 * EmailMessage-Subklassen-Falle: KEINE Props mit Basisklassen-Namen ($email/$name/...) — daher
 * durchgängig zhl-präfixierte Eigenschaften (vgl. ZhlHauspostEmail).
 */
class ZhlMediaInfoEmail extends EmailMessage
{
    private $zhlTo;     // EmailAddress[]
    private $zhlRef;
    private $zhlBody;

    /**
     * @param EmailAddress[] $to
     * @param string $referenceNumber  Buchungsnummer (für den Betreff)
     * @param string $body             fertiger Klartext-Body
     */
    public function __construct(array $to, string $referenceNumber, string $body, $language = null)
    {
        $this->zhlTo = $to;
        $this->zhlRef = $referenceNumber;
        $this->zhlBody = $body;
        parent::__construct($language);
    }

    public function To()
    {
        return $this->zhlTo;
    }

    public function Subject()
    {
        $suffix = $this->zhlRef !== '' ? ' (' . $this->zhlRef . ')' : '';
        return 'ZHL Medienausleihe — Infos zu deinen gebuchten Medien' . $suffix;
    }

    public function Body()
    {
        return nl2br(htmlspecialchars($this->zhlBody, ENT_QUOTES, 'UTF-8'));
    }
}
