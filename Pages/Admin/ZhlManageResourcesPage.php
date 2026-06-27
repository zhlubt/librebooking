<?php

require_once(ROOT_DIR . 'Pages/Admin/ManageResourcesPage.php');

/**
 * ZHL HARD-LOCK „Geräte nie ausblenden".
 *
 * Die Sichtbarkeit eines Geräts soll ausschließlich über den Aktiv-/Deaktiviert-Status
 * gesteuert werden — nicht über die native Berechtigungs-Automatik. Damit ein Gerät nie
 * (versehentlich beim Import, per Admin-Häkchen oder Bulk-Edit) für normale Nutzer
 * unsichtbar wird, erzwingt diese Page beim Speichern:
 *   - „Automatisch allen Nutzern zuweisen" (autoassign) IMMER an,
 *   - „Bestehende Berechtigungen entfernen" (clear all permissions) NIE.
 *
 * So bleibt jedes aktive Gerät für alle sichtbar; das Buchen bleibt unverändert
 * einweisungs-/zertifikatspflichtig (ZhlCertificate-Plugin + zhl_uebergabe).
 *
 * Upgrade-Hinweis: aktiviert über den Swap in Web/admin/manage_resources.php
 * (`new ZhlManageResourcesPage()` statt `new ManageResourcesPage()`).
 */
class ZhlManageResourcesPage extends ManageResourcesPage
{
    /** Einzel-Gerät anlegen/ändern: autoassign immer an. */
    public function GetAutoAssign()
    {
        return true;
    }

    /** Bulk-Edit: autoassign immer an (1 = „Ja"; ChangingDropDown-Sentinel für „unverändert" ist -1). */
    public function GetBulkAutoAssign()
    {
        return 1;
    }

    /** „Alle bestehenden Berechtigungen entfernen" wird nie angewendet (würde Geräte verstecken). */
    public function GetAutoAssignClear()
    {
        return false;
    }
}
