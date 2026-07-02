<?php

require_once(ROOT_DIR . 'lib/Email/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlTypeInfo.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlEmailLayout.php');

/**
 * ZHL D2 — Bestätigungs-Begleitmail mit Info-Material zu den gebuchten Medien (gebrandetes HTML).
 *
 * Geht nach erfolgreicher Buchung an den Ausleihenden (To) und listet je gebuchtem Geräte-TYP
 * (Kategorie) EINEN Info-Link — mehrere Einheiten desselben Typs (z. B. drei DJI Mics) erscheinen
 * dank ZhlTypeInfo::DedupByType nur einmal. Layout via [[ZhlEmailLayout]] (grüner Kopf #009260).
 * Wird nur versandt, wenn überhaupt ein Info-Link vorhanden ist (Aufrufer prüft das).
 *
 * EmailMessage-Subklassen-Falle: KEINE Props mit Basisklassen-Namen ($email/$name/...) — daher
 * durchgängig zhl-präfixierte Eigenschaften (vgl. ZhlHauspostEmail).
 */
class ZhlMediaInfoEmail extends EmailMessage
{
    private $zhlTo;       // EmailAddress[]
    private $zhlRef;      // Buchungsnummer (Betreff + Subtitle)
    private $zhlName;     // Anrede-Name (kann leer sein)
    private $zhlIntro;    // Intro-Satz (z. B. „vielen Dank für deine Buchung …")
    private $zhlInfos;    // ForReference()-Rohliste (wird dedupliziert gerendert)

    /**
     * @param EmailAddress[] $to
     * @param string $referenceNumber Buchungsnummer
     * @param string $greetingName    Name für die Anrede (leer → „Hallo,")
     * @param string $intro           Einleitungssatz
     * @param array<int,array{device:string,type:string,url:string,text:string}> $infos
     */
    public function __construct(array $to, string $referenceNumber, string $greetingName, string $intro, array $infos, $language = null)
    {
        $this->zhlTo = $to;
        $this->zhlRef = $referenceNumber;
        $this->zhlName = trim($greetingName);
        $this->zhlIntro = trim($intro);
        $this->zhlInfos = $infos;
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
        $h = static fn ($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $greeting = $this->zhlName !== '' ? 'Hallo ' . $this->zhlName . ',' : 'Hallo,';

        $body = '<p style="margin:0 0 14px;">' . $h($greeting) . '</p>';
        if ($this->zhlIntro !== '') {
            $body .= '<p style="margin:0 0 18px;">' . $h($this->zhlIntro) . '</p>';
        }
        $body .= ZhlTypeInfo::EmailListHtml($this->zhlInfos);
        $body .= '<p style="margin:20px 0 0;color:#374151;">Viele Grüße<br>ZHL Medienausleihe</p>';

        return ZhlEmailLayout::Wrap([
            'eyebrow' => 'ZHL Medienausleihe',
            'title' => 'Infos zu deinen gebuchten Medien',
            'subtitle' => $this->zhlRef !== '' ? 'Buchungsnummer ' . $this->zhlRef : '',
            'bodyHtml' => $body,
            'footer' => 'Diese Mail begleitet deine Buchung bei der ZHL-Medienausleihe · Universität Bayreuth. '
                . 'Pro Geräte-Typ ist ein Anleitungs-Link aufgeführt.',
        ]);
    }
}
