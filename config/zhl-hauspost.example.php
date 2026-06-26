<?php
/**
 * ZHL C2 — Hauspost-Versand für analoge Medien — Konfiguration (Vorlage).
 *
 * Kopiere diese Datei nach config/zhl-hauspost.php und passe die Werte an.
 * ZhlBookPresenter liest config/zhl-hauspost.php; existiert sie nicht, fällt es auf
 * diese .example-Datei zurück (gleiches Muster wie config/zhl-handover[.example].php).
 *
 * - transport_email: organisiert den Transport (Stefan Bauernschmitt).
 * - medien_email: ZHL-Medien-Team (stellt das Material bereit, prüft den Antrag).
 * - lead_days: Mindest-Vorlauf in WERKTAGEN zwischen Buchung und Einsatz, damit der
 *   Medienmanager Zeit zum Bereitstellen hat. Greift nur, wenn einsatz_termin als
 *   Datum parsebar ist (Freitext/Zeitraum → weiche Prüfung, siehe Presenter).
 */

return [
    'transport_email' => 'stefan.bauernschmitt@uni-bayreuth.de',
    'medien_email' => 'zhlmedien@uni-bayreuth.de',
    'lead_days' => 3,
];
