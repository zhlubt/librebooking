<?php

define('ROOT_DIR', '../');

if (!file_exists(ROOT_DIR . 'config/config.php')) {
    die('Missing config/config.php. Please refer to the installation instructions.');
}

// ZHL-Anpassung: Login im ZHL-Studio-Look statt der nativen LibreBooking-Seite.
// ZhlLoginPage erbt die komplette Login-Logik von LoginPage und tauscht nur das
// Template (tpl/zhl-login.tpl). Bei einem LibreBooking-Upgrade diese eine Zeile
// erneut anwenden (sonst erscheint wieder der native Login mit LibreBooking-Logo).
require_once(ROOT_DIR . 'Pages/ZhlLoginPage.php');
require_once(ROOT_DIR . 'Presenters/LoginPresenter.php');

$page = new ZhlLoginPage();

if ($page->LoggingIn()) {
    $page->Login();
}

if ($page->ChangingLanguage()) {
    $page->ChangeLanguage();
}

$page->PageLoad();
