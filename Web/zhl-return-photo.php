<?php
/**
 * ZHL Selbst-Rückgabe — geschützter Foto-Stream (SPEC-SELBSTRUECKGABE).
 *
 * Liefert das Ablage-Foto einer gemeldeten Rückgabe (?id=<self_return_id>). Die Fotos liegen PRIVAT
 * unter <root>/uploads/zhl-return/ (root-uploads trägt „deny from all") und sind nur über diesen
 * Endpunkt erreichbar — und nur für das ZHL-Team (Admin) ODER den Eigentümer der Rückgabe.
 *
 * ADDITIV. SecurePage (Login Pflicht). Gibt das Bild oder 403/404 zurück.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(__DIR__ . '/zhl-return-lib.php');

class ZhlReturnPhotoPage extends SecurePage
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

        $id = filter_var($this->GetQuerystring('id'), FILTER_VALIDATE_INT);
        $row = ($id !== false && $id > 0) ? zhl_return_get((int)$id) : null;
        if ($row === null) {
            http_response_code(404);
            echo 'Nicht gefunden.';
            return;
        }

        $isOwner = (int)($row['user_id'] ?? 0) > 0 && (int)$row['user_id'] === (int)$session->UserId;
        if (!$isStaff && !$isOwner) {
            http_response_code(403);
            echo 'Kein Zugriff.';
            return;
        }

        $abs = zhl_return_photo_abs_path((string)($row['photo_path'] ?? ''));
        if ($abs === null) {
            http_response_code(404);
            echo 'Foto nicht vorhanden.';
            return;
        }

        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($abs);
        if (strpos($mime, 'image/') !== 0) {
            $mime = 'application/octet-stream';
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string)filesize($abs));
        header('Cache-Control: private, no-store');
        header('Content-Disposition: inline; filename="rueckgabe-' . (int)$row['id'] . '"');
        readfile($abs);
    }
}

$page = new ZhlReturnPhotoPage();
$page->PageLoad();
