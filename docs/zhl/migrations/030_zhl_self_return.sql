-- 030: Selbst-Rückgabe kleiner Medien (SPEC-SELBSTRUECKGABE, 2026-06-29).
--
-- Kleine Medien (zhl_uebergabe.rueckgabe IN ('abgeben','nicht_noetig')) dürfen jederzeit an einem
-- vorbereiteten Ort (Cateringwagen / Videostudio) abgelegt werden: QR vor Ort → Drop-Seite → Login
-- ODER E-Mail-Magic-Link → Auswahl der abgelegten Medien → Pflicht-Foto → Mail an alle Beteiligten.
-- Die Ablage wird als 'reported' protokolliert; das Gerät bleibt gesperrt, bis das Team es über die
-- bestehende Checkliste (zhl-handover-check.php) als 'done' bestätigt (dann self_return → 'confirmed').
--
-- Pflicht-Rückgabe ('abgeben_persoenlich', SPEC-RUECKGABE) bleibt unangetastet.
-- Rein additiv. Idempotent: CREATE TABLE IF NOT EXISTS + INSERT ... ON DUPLICATE KEY UPDATE (no-op).
-- QR-Token der Seed-Standorte via SHA2/UUID/RAND (portabel auf MariaDB >= 10.6 UND MySQL >= 8;
-- bewusst KEIN RANDOM_BYTES, das es in MariaDB erst ab 10.10 gibt).

-- 1) Ablage-Standorte (je eigener QR) ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS zhl_return_location (
  id         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug       VARCHAR(40)  NOT NULL,                 -- stabiler Schlüssel: 'cateringwagen','videostudio'
  label      VARCHAR(120) NOT NULL,                 -- Anzeigename
  qr_token   VARCHAR(64)  NOT NULL,                 -- unrat-bares Token in der QR-URL (?loc=…)
  active     TINYINT(1)   NOT NULL DEFAULT 1,
  note       VARCHAR(255) NULL,                     -- optionaler Hinweis auf der Drop-Seite
  created_at DATETIME     NOT NULL,
  updated_at DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_slug (slug),
  UNIQUE KEY uq_qr_token (qr_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed der zwei Standorte. qr_token nur beim ERST-Insert (SHA2/UUID/RAND); Re-Run ist no-op (slug=slug),
-- damit der bereits gedruckte QR stabil bleibt. Pro Umgebung eigene Tokens (gewollt).
INSERT INTO zhl_return_location (slug, label, qr_token, active, note, created_at)
VALUES
  ('cateringwagen', 'Cateringwagen', LEFT(SHA2(CONCAT(RAND(), UUID(), 'cateringwagen'), 256), 32), 1,
   'Bitte die Medien sichtbar in die vorgesehene Box im Cateringwagen legen und hier fotografieren.', NOW()),
  ('videostudio',   'Videostudio',   LEFT(SHA2(CONCAT(RAND(), UUID(), 'videostudio'), 256), 32), 1,
   'Bitte die Medien an der vorgesehenen Ablage im Videostudio platzieren und hier fotografieren.', NOW())
ON DUPLICATE KEY UPDATE slug = slug;

-- 2) Gemeldete Selbst-Rückgaben (1 Zeile je abgelegtem Gerät) ----------------------------------------
CREATE TABLE IF NOT EXISTS zhl_self_return (
  id                          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  location_id                 SMALLINT UNSIGNED NOT NULL,   -- → zhl_return_location.id
  user_id                     MEDIUMINT UNSIGNED NULL,      -- LB-User (falls bekannt)
  email                       VARCHAR(190) NULL,            -- Snapshot (Magic-Link-Fall)
  handover_id                 INT UNSIGNED NULL,            -- → zhl_booking_handover.id (type='return')
  reference_number            VARCHAR(32) NULL,
  resource_id                 SMALLINT UNSIGNED NULL,
  photo_path                  VARCHAR(255) NULL,            -- relativ unter uploads/zhl-return/
  note                        VARCHAR(500) NULL,
  reported_at                 DATETIME NOT NULL,            -- Ablage-Zeitpunkt (UTC)
  status                      VARCHAR(16) NOT NULL DEFAULT 'reported', -- reported | confirmed | cancelled
  confirmed_handover_check_id INT UNSIGNED NULL,            -- Verknüpfung zur Team-Checkliste
  created_at                  DATETIME NOT NULL,
  PRIMARY KEY (id),
  INDEX idx_status (status),
  INDEX idx_handover (handover_id),
  INDEX idx_resource (resource_id),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Login-freie Magic-Link-Tokens für die E-Mail-Identifikation ------------------------------------
CREATE TABLE IF NOT EXISTS zhl_return_access (
  token       CHAR(40) NOT NULL,                    -- bin2hex(random_bytes(20))
  user_id     MEDIUMINT UNSIGNED NOT NULL,
  location_id SMALLINT UNSIGNED NULL,               -- Standort-Kontext (woher angefragt)
  created_at  DATETIME NOT NULL,
  expires_at  DATETIME NOT NULL,                    -- z. B. +2h
  used_at     DATETIME NULL,
  PRIMARY KEY (token),
  INDEX idx_user (user_id),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) Default-Team-Empfänger für die Benachrichtigung (additiv, überschreibbar) ----------------------
INSERT IGNORE INTO zhl_settings (k, v, updated_at)
VALUES ('self_return_notify_email', 'zhlmedien@uni-bayreuth.de', NOW());
