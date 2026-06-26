<?php

define('ROOT_DIR', '../');

// ZHL-Anpassung: Registrierung im ZHL-Studio-Look mit Datensparsamkeit (nur E-Mail/Vorname/Nachname/
// Passwort, username = E-Mail). ZhlRegisterPage erbt die komplette Registrierungslogik von
// RegistrationPage und tauscht nur Formular/Felder (tpl/zhl-register.tpl). Bei einem LibreBooking-Upgrade
// diese eine Zeile erneut anwenden (sonst erscheint wieder das native Registrierungsformular).
require_once(ROOT_DIR . 'Pages/ZhlRegisterPage.php');

$page = new ZhlRegisterPage();

$page->PageLoad();
