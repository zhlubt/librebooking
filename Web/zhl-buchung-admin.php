<?php

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/ZhlBuchungAdminPage.php');

$page = new ZhlBuchungAdminPage();
$page->PageLoad();
