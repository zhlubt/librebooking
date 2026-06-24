<?php

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/ZhlCertificatesAdminPage.php');

$page = new ZhlCertificatesAdminPage();
$page->PageLoad();
