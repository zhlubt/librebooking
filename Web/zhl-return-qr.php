<?php
/**
 * ZHL Selbst-Rückgabe — Standort-QR (SPEC-SELBSTRUECKGABE).
 *
 * Kodiert die STABILE absolute Drop-URL eines Ablageorts:
 *   zhl-return-drop.php?loc=<qr_token>
 * Einmal drucken, am Ablageort (Cateringwagen / Videostudio) anbringen.
 *
 * ADDITIV, ADMIN-only. SecurePage + Admin-Check. Gibt image/png aus.
 * QR-Rendering identisch zu zhl-resource-qr.php (BaconQrCode / GDLibRenderer / Writer).
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(__DIR__ . '/zhl-return-lib.php');

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

class ZhlReturnQrPage extends SecurePage
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

        $locId = filter_var($this->GetQuerystring('loc'), FILTER_VALIDATE_INT);
        $location = ($locId !== false && $locId > 0) ? zhl_return_location_by_id((int)$locId) : null;
        if ($location === null) {
            http_response_code(404);
            echo 'Unbekannter Ablageort.';
            return;
        }

        $base = rtrim((string)Configuration::Instance()->GetScriptUrl(), '/');
        $target = $base . '/zhl-return-drop.php?loc=' . urlencode((string)$location['qr_token']);

        $writer = new Writer(new GDLibRenderer(360));
        $png = $writer->writeString($target);

        header('Content-Type: image/png');
        header('Cache-Control: no-store');
        echo $png;
    }
}

$page = new ZhlReturnQrPage();
$page->PageLoad();
