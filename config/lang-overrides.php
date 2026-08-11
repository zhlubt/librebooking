<?php
/*
 * ZHL-Sprach-Overrides (upgrade-sicher; überschreibt KEINE Sprachdateien).
 * Vereinfacht Begriffe für Standard-User: „Ressource" -> „Gerät".
 * Admin-Fachbegriffe (ResourceType, ResourceGroups, …) bleiben bewusst unangetastet.
 * Keys müssen exakt den LibreBooking-Translation-Keys entsprechen (case-sensitiv).
 *
 * Die "Zhl*"-Keys sind eigene, NEU eingeführte Schlüssel für die ZHL-Oberflächen
 * (zhl-dashboard, zhl-account, Navigation). array_merge in Resources fügt sie der
 * Sprachtabelle hinzu, daher MUSS jeder Zhl*-Key in JEDER aktiven Sprache (de_de
 * UND en_us) definiert sein — fehlt er, rendert {translate} ein "?".
 */

// Global (alle Sprachen) — hier leer, wir bleiben sprachspezifisch.
$langOverrides = [];

// Gemeinsame ZHL-UI-Keys, in beiden Sprachen identisch gepflegt.
$zhlCommon = [
    'de_de' => [
        // --- Navigation (globalheader.tpl) + gemeinsame Begriffe ---
        'ZhlDevices'              => 'Geräte',
        'ZhlMyBookings'           => 'Meine Buchungen',
        'ZhlNavAccount'           => 'Konto',
        'ZhlNavCertificates'      => 'Einführungs-Zertifikate',
        'ZhlNavBundles'           => 'Geräte-Bundles',
        'ZhlNavHandovers'         => 'Persönliche Übergaben',
        'ZhlNavTermine'           => 'Geplante Termine',
        'ZhlNavAusleihen'         => 'Anstehende Ausleihen',
        'ZhlImprint'              => 'Impressum',
        'ZhlPrivacy'              => 'Datenschutz',
        'ZhlLogout'               => 'Abmelden',
        'ZhlDays'                 => 'Tage',

        // --- Dashboard (zhl-dashboard.tpl) ---
        'ZhlDashUplabel'          => 'Medienausleihe ZHL',
        'ZhlDashTitle'            => 'Geräte einzeln buchen',
        'ZhlDashIntro'            => 'Wähle eine Kategorie und einen Zeitraum — du siehst sofort, welche Geräte frei sind.',
        'ZhlDashVisibleSuffix'    => 'Geräte für dich sichtbar',
        'ZhlAdminBundles'         => 'Bundles',
        'ZhlAdminCerts'           => 'Zertifikate',
        'ZhlModeBundles'          => 'Bundles buchen',
        'ZhlModeSingle'           => 'Geräte einzeln buchen',
        'ZhlSearchLabel'          => 'Suche',
        'ZhlSearchPlaceholder'    => 'Gerät oder Typ suchen, z. B. Funkmikrofon…',
        'ZhlFromLabel'            => 'Ab',
        'ZhlPreviewLabel'         => 'Vorschau',
        'ZhlPreviewHint'          => 'Wie viele Tage Verfügbarkeit das Raster zeigt (die Ausleihdauer wählst du beim Buchen).',
        'ZhlShow'                 => 'Anzeigen',
        'ZhlCategories'           => 'Kategorien',
        'ZhlAll'                  => 'Alle',
        'ZhlAvailPerType'         => 'Verfügbar je Typ:',
        'ZhlDaysFree'             => 'Tage frei',
        'ZhlLeadTime'             => 'Vorlauf',
        'ZhlFullyBooked'          => 'ausgebucht',
        'ZhlStateFree'            => 'frei',
        'ZhlStateLeadTimeLong'    => 'Vorlauf, noch nicht buchbar',
        'ZhlStateBusy'            => 'belegt',
        'ZhlEarliestStart'        => 'frühester Start',
        'ZhlNextFreeDay'          => 'Nächster freier Tag',
        'ZhlBook'                 => 'Buchen',
        'ZhlBookableFrom'         => 'Buchbar ab',
        'ZhlFullyBookedRange'     => 'Im Zeitraum komplett belegt',
        'ZhlNoDevices'            => 'Keine Geräte für diese Auswahl. Versuch eine andere Kategorie, einen anderen Zeitraum oder Suchbegriff.',
        'ZhlPrototypeNote'        => 'Prototyp: Verfügbarkeit als <strong>Prognose</strong>; die verbindliche Prüfung erfolgt beim Buchen.',

        // --- Ausleihen/Rückgaben/Übergaben (zhl-ausleihen/-rueckgaben/-uebergaben.tpl) ---
        'ZhlAusleihenTitle'       => 'Ausleihen',
        'ZhlRueckgabenTitle'      => 'Rückgaben',
        'ZhlUebergabenTitle'      => 'Persönliche Übergaben',
        'ZhlGeraetAdminTitle'     => 'Geräteakte',

        // --- Konto (zhl-account.tpl) ---
        'ZhlAccountTitle'         => 'Mein Konto',
        'ZhlAccountSub'           => 'Ihre Kontaktdaten auf einen Blick. Änderungen nehmen Sie über die Schaltflächen vor.',
        'ZhlAccountPersonalData'  => 'Persönliche Daten',
        'ZhlAccountFirstName'     => 'Vorname',
        'ZhlAccountLastName'      => 'Nachname',
        'ZhlAccountEmailLabel'    => 'Uni-Bayreuth-Mailadresse',
        'ZhlAccountEmailHint'     => 'Ihre E-Mail ist Ihr Login und kann hier nicht geändert werden.',
        'ZhlAccountPhone'         => 'Telefon',
        'ZhlAccountOrganization'  => 'Einrichtung / Lehrstuhl',
        'ZhlNotProvided'          => 'nicht hinterlegt',
        'ZhlAccountEditData'      => 'Daten bearbeiten',
        'ZhlAccountMyCertificates' => 'Meine Zertifikate',
        'ZhlManage'               => 'Verwalten',
        'ZhlAccountCertHint'      => 'Hier sehen Sie, für welche Geräte Sie die nötige Einführung bereits absolviert haben. Diese Geräte können Sie ohne weiteren Einführungstermin buchen (die Abholung bleibt erforderlich).',
        'ZhlAccountCertGranted'   => 'Erworben am',
        'ZhlAccountCertExpiredOn' => 'abgelaufen am',
        'ZhlAccountCertValidUntil' => 'gültig bis',
        'ZhlAccountCertUnlimited' => 'unbegrenzt gültig',
        'ZhlAccountCertBadgeExpired' => 'abgelaufen',
        'ZhlAccountCertBadgeActive' => 'aktiv',
        'ZhlAccountNoCerts'       => 'Sie haben noch keine Einführungen absolviert. Bei der Buchung eines einführungspflichtigen Geräts schlagen wir Ihnen einen Einführungstermin vor.',
        'ZhlAccountPassword'      => 'Passwort',
        'ZhlAccountPasswordHint'  => 'Aus Sicherheitsgründen ändern Sie Ihr Passwort über die geschützte Passwortseite.',
        'ZhlAccountChangePassword' => 'Passwort ändern',
        'ZhlAccountSettings'      => 'Einstellungen',
        'ZhlAccountSettingsHint'  => 'Sprache und Benachrichtigungen verwalten Sie im vollständigen Profil.',
        'ZhlAccountOpenProfile'   => 'Profil öffnen',
        'ZhlAccountSeparateNote'  => 'Dieses Konto gilt nur für die Medienausleihe. Die zentrale Uni-Anmeldung (bt-Kennung) ist davon getrennt.',
    ],
    'en_us' => [
        // --- Navigation (globalheader.tpl) + shared terms ---
        'ZhlDevices'              => 'Devices',
        'ZhlMyBookings'           => 'My Bookings',
        'ZhlNavAccount'           => 'Account',
        'ZhlNavCertificates'      => 'Induction Certificates',
        'ZhlNavBundles'           => 'Device Bundles',
        'ZhlNavHandovers'         => 'Personal handovers',
        'ZhlNavTermine'           => 'Planned appointments',
        'ZhlNavAusleihen'         => 'Upcoming Pickups',
        'ZhlImprint'              => 'Legal notice',
        'ZhlPrivacy'              => 'Privacy policy',
        'ZhlLogout'               => 'Sign out',
        'ZhlDays'                 => 'days',

        // --- Dashboard (zhl-dashboard.tpl) ---
        'ZhlDashUplabel'          => 'ZHL Media Lending',
        'ZhlDashTitle'            => 'Book individual devices',
        'ZhlDashIntro'            => 'Choose a category and a period — you will instantly see which devices are free.',
        'ZhlDashVisibleSuffix'    => 'devices visible to you',
        'ZhlAdminBundles'         => 'Bundles',
        'ZhlAdminCerts'           => 'Certificates',
        'ZhlModeBundles'          => 'Book bundles',
        'ZhlModeSingle'           => 'Book individual devices',
        'ZhlSearchLabel'          => 'Search',
        'ZhlSearchPlaceholder'    => 'Search device or type, e.g. wireless microphone…',
        'ZhlFromLabel'            => 'From',
        'ZhlPreviewLabel'         => 'Preview',
        'ZhlPreviewHint'          => 'How many days of availability the grid shows (you choose the lending duration when booking).',
        'ZhlShow'                 => 'Show',
        'ZhlCategories'           => 'Categories',
        'ZhlAll'                  => 'All',
        'ZhlAvailPerType'         => 'Available per type:',
        'ZhlDaysFree'             => 'days free',
        'ZhlLeadTime'             => 'Lead time',
        'ZhlFullyBooked'          => 'fully booked',
        'ZhlStateFree'            => 'free',
        'ZhlStateLeadTimeLong'    => 'lead time, not yet bookable',
        'ZhlStateBusy'            => 'busy',
        'ZhlEarliestStart'        => 'earliest start',
        'ZhlNextFreeDay'          => 'Next free day',
        'ZhlBook'                 => 'Book',
        'ZhlBookableFrom'         => 'Bookable from',
        'ZhlFullyBookedRange'     => 'Fully booked in this period',
        'ZhlNoDevices'            => 'No devices for this selection. Try another category, period or search term.',
        'ZhlPrototypeNote'        => 'Prototype: availability shown as a <strong>forecast</strong>; the binding check happens when you book.',

        // --- Ausleihen/Rückgaben/Übergaben (zhl-ausleihen/-rueckgaben/-uebergaben.tpl) ---
        'ZhlAusleihenTitle'       => 'Pickups',
        'ZhlRueckgabenTitle'      => 'Returns',
        'ZhlUebergabenTitle'      => 'Personal handovers',
        'ZhlGeraetAdminTitle'     => 'Device details',

        // --- Account (zhl-account.tpl) ---
        'ZhlAccountTitle'         => 'My Account',
        'ZhlAccountSub'           => 'Your contact details at a glance. Make changes using the buttons.',
        'ZhlAccountPersonalData'  => 'Personal details',
        'ZhlAccountFirstName'     => 'First name',
        'ZhlAccountLastName'      => 'Last name',
        'ZhlAccountEmailLabel'    => 'University of Bayreuth email',
        'ZhlAccountEmailHint'     => 'Your email is your login and cannot be changed here.',
        'ZhlAccountPhone'         => 'Phone',
        'ZhlAccountOrganization'  => 'Institution / Chair',
        'ZhlNotProvided'          => 'not provided',
        'ZhlAccountEditData'      => 'Edit details',
        'ZhlAccountMyCertificates' => 'My certificates',
        'ZhlManage'               => 'Manage',
        'ZhlAccountCertHint'      => 'Here you can see which devices you have already completed the required induction for. You can book these devices without a further induction appointment (pick-up is still required).',
        'ZhlAccountCertGranted'   => 'Obtained on',
        'ZhlAccountCertExpiredOn' => 'expired on',
        'ZhlAccountCertValidUntil' => 'valid until',
        'ZhlAccountCertUnlimited' => 'valid indefinitely',
        'ZhlAccountCertBadgeExpired' => 'expired',
        'ZhlAccountCertBadgeActive' => 'active',
        'ZhlAccountNoCerts'       => 'You have not completed any inductions yet. When you book a device that requires an induction, we will suggest an induction appointment.',
        'ZhlAccountPassword'      => 'Password',
        'ZhlAccountPasswordHint'  => 'For security reasons, change your password on the protected password page.',
        'ZhlAccountChangePassword' => 'Change password',
        'ZhlAccountSettings'      => 'Settings',
        'ZhlAccountSettingsHint'  => 'Manage language and notifications in your full profile.',
        'ZhlAccountOpenProfile'   => 'Open profile',
        'ZhlAccountSeparateNote'  => 'This account is only for media lending. The central university login (bt account) is separate from it.',
    ],
];

// Pro Sprache: Deutsch (Begriffs-Vereinfachung) + ZHL-UI-Keys.
$langOverridesByLanguage = [
    'de_de' => array_merge([
        'Resource'      => 'Gerät',
        'Resources'     => 'Geräte',
        'AllResources'  => 'Alle Geräte',
        'ResourceList'  => 'Zu reservierende Geräte',
        'AddResource'   => 'Gerät hinzufügen',
        'AddResources'  => 'Geräte hinzufügen',
        'ResourceFilter' => 'Gerätefilter',
    ], $zhlCommon['de_de']),
    'en_us' => $zhlCommon['en_us'],
];
