<?php

define('ROOT_DIR', '../');

// ZHL-Anpassung: Aktivierung fängt das Microsoft/Outlook-"Safe Links"-Vorab-Abruf-Problem ab
// (Einmal-Token wird beim automatischen Link-Check verbraucht → der menschliche Klick landet
// sonst auf einer Fehlerseite). ZhlActivationPage leitet diesen Fall freundlich zum Login um.
// Bei einem LibreBooking-Upgrade diese eine Zeile erneut anwenden.
require_once(ROOT_DIR . 'Pages/ZhlActivationPage.php');

$page = new ZhlActivationPage();
$page->PageLoad();
