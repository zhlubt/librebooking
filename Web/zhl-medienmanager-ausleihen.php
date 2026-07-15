<?php

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/ZhlAusleihenPage.php');

$page = new ZhlAusleihenPage();
$page->PageLoad();
