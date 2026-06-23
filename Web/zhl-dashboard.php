<?php

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/ZhlDashboardPage.php');

$page = new ZhlDashboardPage();
$page->PageLoad();
