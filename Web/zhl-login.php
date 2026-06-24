<?php
/**
 * ZHL: Einstieg für die ZHL-Login-Seite (eigenständig, upgrade-sicher).
 * Spiegelt index.php, nutzt aber ZhlLoginPage (eigenes Template).
 */

define('ROOT_DIR', '../');

if (!file_exists(ROOT_DIR . 'config/config.php')) {
    die('Missing config/config.php. Please refer to the installation instructions.');
}

require_once(ROOT_DIR . 'Pages/ZhlLoginPage.php');

$page = new ZhlLoginPage();

if ($page->LoggingIn()) {
    $page->Login();
}

if ($page->ChangingLanguage()) {
    $page->ChangeLanguage();
}

$page->PageLoad();
