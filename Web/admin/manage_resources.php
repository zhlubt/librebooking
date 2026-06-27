<?php

define('ROOT_DIR', '../../');

require_once(ROOT_DIR . 'Pages/Admin/ManageResourcesPage.php');
require_once(ROOT_DIR . 'Presenters/Admin/ManageResourcesPresenter.php');
// ZHL HARD-LOCK: ZhlManageResourcesPage erzwingt autoassign=an / clear=nie, damit Geräte nie
// ausgeblendet werden können (Sichtbarkeit nur über Aktiv/Deaktiviert). Bei Upgrade erneut anwenden.
require_once(ROOT_DIR . 'Pages/Admin/ZhlManageResourcesPage.php');

$page = new AdminPageDecorator(new ZhlManageResourcesPage());
$page->PageLoad();
