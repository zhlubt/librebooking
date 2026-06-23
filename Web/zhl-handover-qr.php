<?php
/**
 * ZHL Übergabe-QR (Phase B).
 *
 * Erzeugt einen ZHL-eigenen QR-Code (PNG), der auf die Übergabe-Checkliste
 * (Web/zhl-handover-check.php) zeigt — NICHT auf den nativen Resource-QR-Router,
 * der zur Reservierung führt. So kann das ZHL-Team beim Aus-/Rückgabetermin den
 * Code scannen und direkt das Protokoll erfassen.
 *
 * Nur fürs ZHL-Team (Admin). SecurePage + Admin-Check. Gibt image/png aus.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

class ZhlHandoverQrPage extends SecurePage
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

        $ref = preg_replace('/[^A-Za-z0-9._-]/', '', (string)$this->GetQuerystring('ref'));
        $token = (string)$this->GetQuerystring('token');
        if (!preg_match('/^[A-Za-z0-9]{8,64}$/', $token)) {
            $token = '';
        }
        $type = $this->GetQuerystring('type') === 'return' ? 'return' : 'pickup';
        $resourceId = (int)$this->GetQuerystring('resource');

        $base = rtrim(Configuration::Instance()->GetScriptUrl(), '/');
        $target = $base . '/zhl-handover-check.php?ref=' . urlencode($ref)
            . '&token=' . urlencode($token) . '&type=' . urlencode($type) . '&resource=' . $resourceId;

        $writer = new Writer(new GDLibRenderer(320));
        $png = $writer->writeString($target);

        header('Content-Type: image/png');
        header('Cache-Control: no-store');
        echo $png;
    }
}

$page = new ZhlHandoverQrPage();
$page->PageLoad();
