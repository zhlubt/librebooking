<?php

require_once(ROOT_DIR . 'lib/Email/namespace.php');

/**
 * ZHL — Rückgabe-Erinnerungen/Mahnungen (Klartext, bilingual DE/EN nach Empfänger-Sprache).
 *
 * Drei Stufen, Wording aus docs/zhl/MAIL-TEMPLATES-ENTWURF.md (Sie-Form, geerdet):
 *   1 = Vortag-Erinnerung (freundlich, automatisch)
 *   2 = überfällig, deutlich (erst nach Admin-Freigabe)
 *   3 = letzte Erinnerung (erst nach Admin-Freigabe)
 *
 * EmailMessage-Subklassen-Falle: nur zhl-präfixierte Props (vgl. ZhlHauspostEmail).
 */
class ZhlReturnMail extends EmailMessage
{
    public const STAGE_REMINDER = 1; // Vortag
    public const STAGE_OVERDUE = 2;  // deutlich
    public const STAGE_FINAL = 3;    // letzte

    private $zhlTo;
    private $zhlStage;
    private $zhlName;
    private $zhlResource;
    private $zhlDue;       // Fälligkeits-/Rückgabedatum (lokal, vorformatiert)
    private $zhlContact;
    private $zhlEnglish;

    public function __construct(EmailAddress $to, int $stage, string $name, string $resource, string $dueLabel, string $contact, $language = null)
    {
        $this->zhlTo = $to;
        $this->zhlStage = $stage;
        $this->zhlName = $name;
        $this->zhlResource = $resource;
        $this->zhlDue = $dueLabel;
        $this->zhlContact = $contact;
        $this->zhlEnglish = is_string($language) && stripos($language, 'en') === 0;
        parent::__construct($language);
    }

    public function To()
    {
        return [$this->zhlTo];
    }

    public function Subject()
    {
        $g = $this->zhlResource;
        if ($this->zhlEnglish) {
            switch ($this->zhlStage) {
                case self::STAGE_REMINDER: return 'Reminder: ' . $g . ' is due back tomorrow';
                case self::STAGE_FINAL: return 'Final reminder: please return ' . $g . ' now';
                default: return 'Overdue: please return ' . $g;
            }
        }
        switch ($this->zhlStage) {
            case self::STAGE_REMINDER: return 'Erinnerung: morgen geben Sie ' . $g . ' zurück';
            case self::STAGE_FINAL: return 'Letzte Erinnerung: ' . $g . ' bitte sofort zurückgeben';
            default: return 'Überfällig: bitte ' . $g . ' zurückgeben';
        }
    }

    public function Body()
    {
        $text = $this->zhlEnglish ? $this->bodyEn() : $this->bodyDe();
        return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }

    private function bodyDe(): string
    {
        $name = $this->zhlName !== '' ? $this->zhlName : 'zusammen';
        $g = $this->zhlResource;
        $f = $this->zhlDue;
        $k = $this->zhlContact;
        if ($this->zhlStage === self::STAGE_REMINDER) {
            return "Hallo $name,\n\n"
                . "kurze Erinnerung: morgen, am $f, ist Ihr vereinbarter Rückgabetermin für $g. "
                . "Bitte bringen Sie es wie abgesprochen zurück, damit es pünktlich für die nächste Person bereitsteht.\n\n"
                . "Passt der Termin nicht mehr? Antworten Sie einfach auf diese Mail.\n\n"
                . "Viele Grüße\nZHL Medienausleihe";
        }
        if ($this->zhlStage === self::STAGE_FINAL) {
            return "Hallo $name,\n\n"
                . "$g ist seit dem $f überfällig, und wir haben Sie bereits erinnert. Dies ist unsere letzte Erinnerung.\n\n"
                . "Bitte geben Sie das Gerät jetzt zurück. Andernfalls müssen wir Ihr Ausleih-Konto vorübergehend sperren, "
                . "bis das Material wieder da ist.\n\n"
                . "Wenn etwas im Weg steht oder das Gerät bereits zurück ist, melden Sie sich bitte heute noch unter $k.\n\n"
                . "Viele Grüße\nZHL Medienausleihe";
        }
        return "Hallo $name,\n\n"
            . "$g sollte am $f zurück sein und ist es noch nicht. Solche Geräte sind bei uns oft direkt im Anschluss "
            . "an die nächste Person verliehen — eine verspätete Rückgabe bringt also schnell die Planung anderer durcheinander.\n\n"
            . "Bitte geben Sie es heute oder spätestens morgen zurück. Falls Sie es bereits abgegeben haben "
            . "(z. B. an einem Ablageort), melden Sie sich bitte kurz unter $k, dann haken wir das ab.\n\n"
            . "Viele Grüße\nZHL Medienausleihe";
    }

    private function bodyEn(): string
    {
        $name = $this->zhlName !== '' ? $this->zhlName : 'there';
        $g = $this->zhlResource;
        $f = $this->zhlDue;
        $k = $this->zhlContact;
        if ($this->zhlStage === self::STAGE_REMINDER) {
            return "Hi $name,\n\n"
                . "a quick reminder: tomorrow, $f, is your agreed return date for $g. "
                . "Please bring it back as arranged so it is ready on time for the next person.\n\n"
                . "Does the date no longer work? Simply reply to this email.\n\n"
                . "Best regards\nZHL Media Lending";
        }
        if ($this->zhlStage === self::STAGE_FINAL) {
            return "Hi $name,\n\n"
                . "$g has been overdue since $f, and we have already reminded you. This is our final reminder.\n\n"
                . "Please return the device now. Otherwise we will have to suspend your lending account temporarily "
                . "until the equipment is back.\n\n"
                . "If something is in the way, or the device is already back, please reach us today at $k.\n\n"
                . "Best regards\nZHL Media Lending";
        }
        return "Hi $name,\n\n"
            . "$g was due back on $f and has not been returned. Devices like this are often lent straight on to the "
            . "next person, so a late return quickly upsets other people's plans.\n\n"
            . "Please return it today or tomorrow at the latest. If you have already returned it (e.g. to a drop-off point), "
            . "just let us know at $k and we will close it off.\n\n"
            . "Best regards\nZHL Media Lending";
    }
}
