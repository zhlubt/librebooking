<?php

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/ZhlBookPage.php');

$page = new ZhlBookPage();
$page->PageLoad();
