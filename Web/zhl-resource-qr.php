<?php
/**
 * ZHL Material-QR — STABILER Geräte-QR (Block D3).
 *
 * Im Gegensatz zum pro-Übergabe-QR (zhl-handover-qr.php) ist dieser QR PRO GERÄT
 * stabil: einmal drucken, aufs Gerät kleben. Er kodiert die absolute URL der
 * Rückgabe-Bestätigungsseite (zhl-resource-return.php?resource=<id>), die beim Scan
 * die aktuell offene Rückgabe des Geräts auflöst und an die bestehende Checkliste
 * weiterleitet.
 *
 * ADDITIV, ADMIN-only. SecurePage + Admin-Check. Gibt image/png aus.
 * QR-Rendering identisch zu zhl-handover-qr.php (BaconQrCode / GDLibRenderer / Writer).
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

class ZhlResourceQrPage extends SecurePage
{
    public function __construct()
    {
        parent::__construct('');
    }

    public function PageLoad()
    {
        $session = $this->server->GetUserSession();
        $isStaff = $session->IsAdmin || $session->IsResourceAdmin
            || $session->IsScheduleAdmin || $session->IsGroupAdmin;
        if (!$isStaff) {
            http_response_code(403);
            echo 'Nur für das ZHL-Team (Admin).';
            return;
        }

        $resourceId = filter_var($this->GetQuerystring('resource'), FILTER_VALIDATE_INT);
        if ($resourceId === false || $resourceId <= 0) {
            http_response_code(400);
            echo 'Ungültige Geräte-ID.';
            return;
        }

        // STABILE Ziel-URL: pro Gerät fix (kein übergabe-/zeit-spezifischer Parameter),
        // damit der einmal gedruckte Aufkleber dauerhaft gilt.
        $base = rtrim(Configuration::Instance()->GetScriptUrl(), '/');
        $target = $base . '/zhl-resource-return.php?resource=' . $resourceId;

        $writer = new Writer(new GDLibRenderer(320));
        $png = $writer->writeString($target);

        header('Content-Type: image/png');
        header('Cache-Control: no-store');
        echo $png;
    }
}

$page = new ZhlResourceQrPage();
$page->PageLoad();
