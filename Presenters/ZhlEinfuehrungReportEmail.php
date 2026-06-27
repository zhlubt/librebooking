<?php

require_once(ROOT_DIR . 'lib/Email/namespace.php');

/**
 * ZHL — wöchentlicher Verfügbarkeits-Report (Einführungs-/Übergabetermine) als HTML-Mail.
 *
 * Der fertige HTML-Body wird von ZhlEinfuehrungReport::RenderHtml() erzeugt und hier nur verpackt.
 * EmailService rendert isHTML(true), daher gibt Body() das HTML unverändert zurück.
 *
 * EmailMessage-Subklassen-Falle: KEINE Props mit Basisklassen-Namen ($email/$name/...) — daher
 * durchgängig zhl-präfixierte Eigenschaften (vgl. ZhlMediaInfoEmail / ZhlHauspostEmail).
 */
class ZhlEinfuehrungReportEmail extends EmailMessage
{
    private $zhlTo;       // EmailAddress[]
    private $zhlSubject;
    private $zhlHtml;

    /**
     * @param EmailAddress[] $to
     */
    public function __construct(array $to, string $subject, string $html, $language = null)
    {
        $this->zhlTo = $to;
        $this->zhlSubject = $subject;
        $this->zhlHtml = $html;
        parent::__construct($language);
    }

    public function To()
    {
        return $this->zhlTo;
    }

    public function Subject()
    {
        return $this->zhlSubject;
    }

    public function Body()
    {
        return $this->zhlHtml;
    }
}
