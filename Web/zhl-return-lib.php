<?php
/**
 * ZHL Selbst-Rückgabe — gemeinsame Helfer (SPEC-SELBSTRUECKGABE, kein direkter Web-Aufruf).
 *
 * Wird eingebunden von:
 *   - Web/zhl-return-drop.php            (öffentliche QR-Landeseite, Login ODER Magic-Link)
 *   - Web/zhl-return-locations-admin.php (Standort-Verwaltung, Admin)
 *   - Web/zhl-return-qr.php              (QR-PNG je Standort)
 *   - Web/zhl-return-photo.php           (geschützter Foto-Stream)
 *   - Web/zhl-resource-return.php        (Staff-Badge), Web/zhl-handover-check.php (Bestätigung)
 *
 * Bewusst leichtgewichtig (raw PDO via zhl_handover_db() aus zhl-handover-lib.php), upgrade-sicher,
 * kein LibreBooking-Core. Mail/URL-Helfer setzen ein gebootstrapptes Framework voraus (ServiceLocator,
 * Configuration) — alle Aufrufer binden es ein.
 */

declare(strict_types=1);

require_once(__DIR__ . '/zhl-handover-lib.php');

if (!defined('ZHL_RETURN_LIB')) {
    define('ZHL_RETURN_LIB', 1);
}

/** Selbst-rückgabefähig = NICHT der Pflicht-Rückgabe-Modus. */
const ZHL_RETURN_EXCLUDED_MODE = 'abgeben_persoenlich';

/** Erlaubte Bild-MIME-Typen → Dateiendung. */
function zhl_return_allowed_image_types(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
    ];
}

/**
 * Absolute Basis-URL zum /Web/-Verzeichnis (für QR-Inhalt + Magic-Links), mit Fallback.
 * `script.url` zeigt bereits auf das Web-Verzeichnis (vgl. zhl-resource-qr.php / zhl_ta_base()),
 * daher NUR den abschließenden Slash ergänzen — kein zweites /Web/.
 */
function zhl_return_base_url(): string
{
    try {
        $url = rtrim((string)Configuration::Instance()->GetScriptUrl(), '/');
        if ($url !== '') {
            return $url . '/';
        }
    } catch (Throwable $e) {
        // Fallback unten
    }
    return '/Web/';
}

/** HTML-Escape-Kurzform. */
function zhl_return_h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// --------------------------------------------------------------------------------------------------
// Standorte
// --------------------------------------------------------------------------------------------------

/** Standort per QR-Token (nur aktive werden für die Drop-Seite zurückgegeben). */
function zhl_return_location_by_qr(string $token, bool $activeOnly = true): ?array
{
    if (!preg_match('/^[a-f0-9]{8,64}$/', $token)) {
        return null;
    }
    $sql = 'SELECT * FROM zhl_return_location WHERE qr_token = ?';
    if ($activeOnly) {
        $sql .= ' AND active = 1';
    }
    $stmt = zhl_handover_db()->prepare($sql . ' LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Standort per id. */
function zhl_return_location_by_id(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = zhl_handover_db()->prepare('SELECT * FROM zhl_return_location WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Alle Standorte (Admin-Liste). */
function zhl_return_locations_all(bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM zhl_return_location';
    if ($activeOnly) {
        $sql .= ' WHERE active = 1';
    }
    $sql .= ' ORDER BY active DESC, label';
    return zhl_handover_db()->query($sql)->fetchAll();
}

/** Neuen Standort anlegen; gibt die neue id zurück. slug aus label abgeleitet, falls leer. */
function zhl_return_location_create(string $label, ?string $slug, ?string $note, bool $active): int
{
    $label = trim($label);
    if ($label === '') {
        throw new InvalidArgumentException('Label fehlt.');
    }
    $slug = $slug !== null && trim($slug) !== '' ? $slug : $label;
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $slug));
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'ort-' . bin2hex(random_bytes(3));
    }
    $token = bin2hex(random_bytes(16));
    $pdo = zhl_handover_db();
    $stmt = $pdo->prepare(
        'INSERT INTO zhl_return_location (slug, label, qr_token, active, note, created_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$slug, $label, $token, $active ? 1 : 0, ($note ?? '') !== '' ? $note : null, gmdate('Y-m-d H:i:s')]);
    return (int)$pdo->lastInsertId();
}

/** Bestehenden Standort aktualisieren (label/note/active). */
function zhl_return_location_update(int $id, string $label, ?string $note, bool $active): void
{
    if ($id <= 0) {
        return;
    }
    $stmt = zhl_handover_db()->prepare(
        'UPDATE zhl_return_location SET label = ?, note = ?, active = ?, updated_at = ? WHERE id = ?'
    );
    $stmt->execute([trim($label), ($note ?? '') !== '' ? $note : null, $active ? 1 : 0, gmdate('Y-m-d H:i:s'), $id]);
}

/** QR-Token eines Standorts neu erzeugen (alter QR wird ungültig). Gibt das neue Token zurück. */
function zhl_return_location_regenerate_token(int $id): ?string
{
    if ($id <= 0) {
        return null;
    }
    $token = bin2hex(random_bytes(16));
    $stmt = zhl_handover_db()->prepare(
        'UPDATE zhl_return_location SET qr_token = ?, updated_at = ? WHERE id = ?'
    );
    $stmt->execute([$token, gmdate('Y-m-d H:i:s'), $id]);
    return $token;
}

// --------------------------------------------------------------------------------------------------
// Offene, selbst-rückgabefähige Medien eines Nutzers
// --------------------------------------------------------------------------------------------------

/**
 * Aktuell ausgeliehene, selbst-rückgabefähige Medien des Nutzers (offene Rückgabe).
 *
 * Quelle = zhl_booking_handover (type='return', status requested/confirmed), zugeordnet zum Nutzer
 * über reference_number → reservation_instances → reservation_series.owner_id. Pflicht-Rückgabe-Geräte
 * (rueckgabe='abgeben_persoenlich') werden ausgeschlossen. Bereits gemeldete (offenes zhl_self_return)
 * werden mitgeliefert, aber markiert (already_reported), damit die UI sie „bereits gemeldet" zeigt.
 *
 * @return array<int,array<string,mixed>> je Zeile: handover_id, reference_number, resource_id,
 *         resource_name, scheduled_end_utc, rueckgabe, rueckgabeort, already_reported(bool)
 */
function zhl_return_open_items_for_user(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }
    $stmt = zhl_handover_db()->prepare(
        "SELECT h.id AS handover_id, h.reference_number, h.resource_id, h.scheduled_end_utc,
                r.name AS resource_name,
                COALESCE(u.rueckgabe, 'abgeben') AS rueckgabe,
                u.rueckgabeort,
                EXISTS(SELECT 1 FROM zhl_self_return sr
                       WHERE sr.handover_id = h.id AND sr.status = 'reported') AS already_reported
         FROM zhl_booking_handover h
         JOIN reservation_instances ri ON ri.reference_number = h.reference_number
         JOIN reservation_series rs ON rs.series_id = ri.series_id
         LEFT JOIN resources r ON r.resource_id = h.resource_id
         LEFT JOIN zhl_uebergabe u ON u.resource_id = h.resource_id
         WHERE h.type = 'return'
           AND h.status IN ('requested','confirmed')
           AND rs.owner_id = ?
           AND COALESCE(u.rueckgabe, 'abgeben') <> ?
         ORDER BY h.scheduled_end_utc IS NULL, h.scheduled_end_utc, h.id"
    );
    $stmt->execute([$userId, ZHL_RETURN_EXCLUDED_MODE]);
    return $stmt->fetchAll();
}

// --------------------------------------------------------------------------------------------------
// Nutzer-Auflösung + Magic-Link
// --------------------------------------------------------------------------------------------------

/** Nutzer per E-Mail (case-insensitive). Gibt user_id/fname/lname/email/language oder null. */
function zhl_return_user_by_email(string $email): ?array
{
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $stmt = zhl_handover_db()->prepare(
        'SELECT user_id, fname, lname, email, language FROM users WHERE email = ? AND status_id = 1 LIMIT 1'
    );
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Nutzer per id (für Anzeige/Mail). */
function zhl_return_user_by_id(int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }
    $stmt = zhl_handover_db()->prepare(
        'SELECT user_id, fname, lname, email, language FROM users WHERE user_id = ? LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Einmal-Magic-Link-Token für 2 h erzeugen; gibt das Token zurück. */
function zhl_return_access_create(int $userId, ?int $locationId): string
{
    $token = bin2hex(random_bytes(20)); // 40 hex
    $now = gmdate('Y-m-d H:i:s');
    $exp = gmdate('Y-m-d H:i:s', time() + 2 * 3600);
    $stmt = zhl_handover_db()->prepare(
        'INSERT INTO zhl_return_access (token, user_id, location_id, created_at, expires_at)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$token, $userId, $locationId, $now, $exp]);
    return $token;
}

/** Token auflösen (gültig, nicht abgelaufen, nicht verbraucht). Gibt user_id/location_id oder null. */
function zhl_return_access_resolve(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
        return null;
    }
    $stmt = zhl_handover_db()->prepare(
        'SELECT token, user_id, location_id FROM zhl_return_access
         WHERE token = ? AND used_at IS NULL AND expires_at > ? LIMIT 1'
    );
    $stmt->execute([$token, gmdate('Y-m-d H:i:s')]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Token ATOMAR einmalig beanspruchen: markiert used_at nur, wenn das Token noch gültig, nicht
 * abgelaufen UND nicht verbraucht ist. Gibt true ausschließlich beim erfolgreichen Erst-Claim zurück
 * (rowCount === 1). Zwei parallele Submits mit demselben Token → nur einer gewinnt (Race-sicher).
 */
function zhl_return_access_claim(string $token): bool
{
    if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
        return false;
    }
    $now = gmdate('Y-m-d H:i:s');
    $stmt = zhl_handover_db()->prepare(
        'UPDATE zhl_return_access SET used_at = ? WHERE token = ? AND used_at IS NULL AND expires_at > ?'
    );
    $stmt->execute([$now, $token, $now]);
    return $stmt->rowCount() === 1;
}

// --------------------------------------------------------------------------------------------------
// Foto-Upload
// --------------------------------------------------------------------------------------------------

/**
 * Hochgeladenes Foto validieren und PRIVAT unter <root>/uploads/zhl-return/<Jahr>/<random>.<ext> ablegen.
 * @param array $file  Eintrag aus $_FILES
 * @return array{ok:bool, path?:string, error?:string}  path = relativ ab uploads/ (z. B. zhl-return/2026/ab…jpg)
 */
function zhl_return_store_photo(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        return ['ok' => false, 'error' => 'Es wurde kein Foto übertragen.'];
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 12 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Das Foto ist zu groß (max. 12 MB).'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($file['tmp_name']);
    $allowed = zhl_return_allowed_image_types();
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'Nur Bilddateien (JPEG, PNG, WebP, HEIC) sind erlaubt.'];
    }
    $ext = $allowed[$mime];

    $root = dirname(__DIR__);
    $year = gmdate('Y');
    $dir = $root . '/uploads/zhl-return/' . $year;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Ablageverzeichnis nicht beschreibbar.'];
    }
    $name = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    $abs = $dir . '/' . $name;
    if (!@move_uploaded_file($file['tmp_name'], $abs)) {
        return ['ok' => false, 'error' => 'Foto konnte nicht gespeichert werden.'];
    }
    @chmod($abs, 0640);
    return ['ok' => true, 'path' => 'zhl-return/' . $year . '/' . $name];
}

/** Absoluter Pfad zu einem gespeicherten Foto (relativ ab uploads/), oder null wenn ungültig/fehlend. */
function zhl_return_photo_abs_path(string $relPath): ?string
{
    // Path-Traversal hart ausschließen.
    if ($relPath === '' || strpos($relPath, '..') !== false || $relPath[0] === '/') {
        return null;
    }
    if (!preg_match('#^zhl-return/[0-9]{4}/[A-Za-z0-9._-]+$#', $relPath)) {
        return null;
    }
    $abs = dirname(__DIR__) . '/uploads/' . $relPath;
    return is_file($abs) ? $abs : null;
}

// --------------------------------------------------------------------------------------------------
// Meldung speichern + Benachrichtigen
// --------------------------------------------------------------------------------------------------

/**
 * Selbst-Rückgabe protokollieren: je übergebenem Handover-Item eine zhl_self_return-Zeile (status
 * 'reported'). Validiert NICHT die Zugehörigkeit — der Aufrufer übergibt ausschließlich Items aus
 * zhl_return_open_items_for_user() (Zugehörigkeit + Selbst-Rückgabefähigkeit dort bereits geprüft).
 *
 * @param array<int,array<string,mixed>> $items  ausgewählte Open-Items (handover_id, reference_number, resource_id)
 * @return int Anzahl gespeicherter Zeilen
 */
function zhl_return_record(int $locationId, ?int $userId, ?string $email, array $items, string $photoPath, ?string $note): int
{
    if ($locationId <= 0 || empty($items) || $photoPath === '') {
        return 0;
    }
    $pdo = zhl_handover_db();
    $now = gmdate('Y-m-d H:i:s');
    $count = 0;
    $pdo->beginTransaction();
    try {
        // Pro Item zuerst unter Sperre prüfen, ob es bereits eine offene Meldung gibt
        // (Gap-Lock auf idx_handover unter REPEATABLE READ serialisiert parallele Submits → keine Doppel).
        $guard = $pdo->prepare(
            "SELECT id FROM zhl_self_return WHERE handover_id = ? AND status = 'reported' FOR UPDATE"
        );
        $stmt = $pdo->prepare(
            'INSERT INTO zhl_self_return
                (location_id, user_id, email, handover_id, reference_number, resource_id,
                 photo_path, note, reported_at, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($items as $it) {
            $hid = isset($it['handover_id']) ? (int)$it['handover_id'] : 0;
            if ($hid > 0) {
                $guard->execute([$hid]);
                if ($guard->fetch()) {
                    $guard->closeCursor();
                    continue; // schon gemeldet → nicht doppelt anlegen
                }
                $guard->closeCursor();
            }
            $stmt->execute([
                $locationId,
                $userId,
                $email,
                $hid > 0 ? $hid : null,
                ($it['reference_number'] ?? '') !== '' ? (string)$it['reference_number'] : null,
                isset($it['resource_id']) ? (int)$it['resource_id'] : null,
                $photoPath,
                ($note ?? '') !== '' ? mb_substr($note, 0, 500) : null,
                $now,
                'reported',
                $now,
            ]);
            $count++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('zhl_return_record: ' . $e->getMessage());
        return 0;
    }
    return $count;
}

/** Team-Empfänger für die Benachrichtigung (zhl_settings.self_return_notify_email, sonst SMTP_FROM). */
function zhl_return_notify_email(): string
{
    $stmt = zhl_handover_db()->prepare("SELECT v FROM zhl_settings WHERE k = 'self_return_notify_email' LIMIT 1");
    $stmt->execute();
    $v = trim((string)$stmt->fetchColumn());
    if ($v !== '' && filter_var($v, FILTER_VALIDATE_EMAIL)) {
        return $v;
    }
    try {
        $from = (string)Configuration::Instance()->GetKey(ConfigKeys::EMAIL_DEFAULT_FROM_ADDRESS);
        if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return $from;
        }
    } catch (Throwable $e) {
        // ignore
    }
    return 'zhlmedien@uni-bayreuth.de';
}

/**
 * Mail an Nutzer UND Team mit Foto-Anhang (Nachweis). Best effort — ein Mailfehler kippt die bereits
 * gespeicherte Meldung NICHT. Setzt gebootstrapptes Framework voraus (EmailService, EmailAddress).
 *
 * @param array<int,array<string,mixed>> $items  gespeicherte Items (resource_name)
 */
function zhl_return_notify(array $userRow, ?string $email, array $location, array $items, string $photoAbsPath, ?string $note): void
{
    try {
        require_once(dirname(__DIR__) . '/Presenters/ZhlTerminRequestEmail.php');

        $userName = trim((string)($userRow['fname'] ?? '') . ' ' . (string)($userRow['lname'] ?? ''));
        $userEmail = trim((string)($userRow['email'] ?? ($email ?? '')));
        $teamEmail = zhl_return_notify_email();
        $locLabel = (string)($location['label'] ?? 'Ablageort');

        $tzName = 'Europe/Berlin';
        try {
            $tzName = (string)Configuration::Instance()->GetDefaultTimezone();
        } catch (Throwable $e) {
            // Fallback
        }
        $whenLocal = (new DateTime('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone($tzName))->format('d.m.Y H:i');

        $deviceLines = [];
        foreach ($items as $it) {
            $deviceLines[] = '  • ' . (($it['resource_name'] ?? '') !== '' ? (string)$it['resource_name'] : 'Gerät #' . (int)($it['resource_id'] ?? 0));
        }

        $lines = [
            ($userName !== '' ? 'Hallo ' . $userName . ',' : 'Hallo,'), '',
            'die folgende Rückgabe wurde soeben gemeldet:',
            '',
            'Ort:       ' . $locLabel,
            'Zeitpunkt: ' . $whenLocal . ' Uhr',
            'Medien:',
        ];
        $lines = array_merge($lines, $deviceLines);
        if (($note ?? '') !== '') {
            $lines[] = '';
            $lines[] = 'Notiz: ' . $note;
        }
        $lines = array_merge($lines, [
            '',
            'Das Foto der Ablage ist als Nachweis angehängt.',
            '',
            'Wichtig: Die Rückgabe ist damit GEMELDET. Das ZHL-Medien-Team holt die Medien vom Ablageort',
            'und schließt die Rückgabe nach einer kurzen Sichtprüfung ab. Erst dann ist das Gerät wieder',
            'ausleihbar. Bei Rückfragen antworten Sie einfach auf diese Mail.',
            '',
            'Viele Grüße',
            'ZHL Medienausleihe',
        ]);
        $body = implode("\n", $lines);

        $to = [];
        if ($userEmail !== '' && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            $to[] = new EmailAddress($userEmail, $userName !== '' ? $userName : $userEmail);
        }
        if ($teamEmail !== '' && strcasecmp($teamEmail, $userEmail) !== 0) {
            $to[] = new EmailAddress($teamEmail, 'ZHL Medien');
        }
        if (empty($to)) {
            return;
        }

        $lang = !empty($userRow['language']) ? (string)$userRow['language'] : null;
        $subject = 'ZHL Medienausleihe — Rückgabe gemeldet (' . $locLabel . ')';
        $mail = new ZhlTerminRequestEmail($to, [], $subject, $body, $lang);

        $bytes = @file_get_contents($photoAbsPath);
        if ($bytes !== false && $bytes !== '') {
            $ext = strtolower(pathinfo($photoAbsPath, PATHINFO_EXTENSION)) ?: 'jpg';
            $mail->AddStringAttachment($bytes, 'rueckgabe-nachweis.' . $ext);
        }
        ServiceLocator::GetEmailService()->Send($mail);
    } catch (Throwable $e) {
        Log::Error('zhl_return_notify: %s', $e);
    }
}

// --------------------------------------------------------------------------------------------------
// Staff-Anzeige + Bestätigung
// --------------------------------------------------------------------------------------------------

/** Jüngste gemeldete (status='reported') Selbst-Rückgabe zu einer Handover-Zeile, oder null. */
function zhl_return_self_for_handover(int $handoverId): ?array
{
    if ($handoverId <= 0) {
        return null;
    }
    $stmt = zhl_handover_db()->prepare(
        "SELECT sr.*, l.label AS location_label
         FROM zhl_self_return sr
         LEFT JOIN zhl_return_location l ON l.id = sr.location_id
         WHERE sr.handover_id = ? AND sr.status = 'reported'
         ORDER BY sr.reported_at DESC, sr.id DESC LIMIT 1"
    );
    $stmt->execute([$handoverId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Jüngste gemeldete Selbst-Rückgabe zu einem Gerät, oder null (Staff-Badge ohne Handover-Kontext). */
function zhl_return_self_for_resource(int $resourceId): ?array
{
    if ($resourceId <= 0) {
        return null;
    }
    $stmt = zhl_handover_db()->prepare(
        "SELECT sr.*, l.label AS location_label
         FROM zhl_self_return sr
         LEFT JOIN zhl_return_location l ON l.id = sr.location_id
         WHERE sr.resource_id = ? AND sr.status = 'reported'
         ORDER BY sr.reported_at DESC, sr.id DESC LIMIT 1"
    );
    $stmt->execute([$resourceId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Eine einzelne Selbst-Rückgabe (für den Foto-Stream). */
function zhl_return_get(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = zhl_handover_db()->prepare('SELECT * FROM zhl_self_return WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Beim Checklisten-Abschluss alle gemeldeten Selbst-Rückgaben einer Handover-Zeile als bestätigt
 * markieren (best effort). Wird aus zhl-handover-check.php nach status='done' aufgerufen.
 */
function zhl_return_mark_confirmed_for_handover(int $handoverId, ?int $checkId): void
{
    if ($handoverId <= 0) {
        return;
    }
    try {
        $stmt = zhl_handover_db()->prepare(
            "UPDATE zhl_self_return
             SET status = 'confirmed', confirmed_handover_check_id = ?
             WHERE handover_id = ? AND status = 'reported'"
        );
        $stmt->execute([$checkId, $handoverId]);
    } catch (Throwable $e) {
        error_log('zhl_return_mark_confirmed_for_handover: ' . $e->getMessage());
    }
}

/**
 * Selbst-Rückgaben bestätigen anhand DERSELBEN Identifikatoren, mit denen die Checkliste die
 * type='return'-Übergabe auf 'done' setzt (Token bevorzugt, sonst reference_number [+ resource_id]).
 * Best effort — wird aus zhl-handover-check.php nach erfolgreichem Abschluss aufgerufen.
 */
function zhl_return_confirm_for_handover_keys(string $token, string $ref, ?int $resourceId, ?int $checkId): void
{
    try {
        $pdo = zhl_handover_db();
        if ($token !== '' && zhl_handover_valid_token($token)) {
            $sql = "SELECT id FROM zhl_booking_handover WHERE type='return' AND handover_token = ?";
            $params = [$token];
        } elseif ($ref !== '' && $resourceId) {
            $sql = "SELECT id FROM zhl_booking_handover WHERE type='return' AND reference_number = ? AND resource_id = ?";
            $params = [$ref, $resourceId];
        } else {
            // Bewusst KEIN reference_number-only-Fallback: der würde bei Multi-Resource-Buchungen alle
            // Return-Zeilen (und ihre Self-Returns) auf einmal bestätigen. Ohne eindeutige Kennung
            // (Token oder ref+resource) wird nichts bestätigt — die Meldung bleibt 'reported' (sichtbar).
            return;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            zhl_return_mark_confirmed_for_handover((int)$r['id'], $checkId);
        }
    } catch (Throwable $e) {
        error_log('zhl_return_confirm_for_handover_keys: ' . $e->getMessage());
    }
}
