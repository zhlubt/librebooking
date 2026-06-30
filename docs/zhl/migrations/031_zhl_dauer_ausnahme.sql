-- 031: Ausleihdauer-Limit + Ausnahme-Anfrage (SPEC-AUSLEIHDAUER-LIMIT, 2026-06-30).
--
-- (A) Pro-Gerät-Override der Standard-Limits (NULL = globaler Default aus zhl_settings):
--       max_nutzung_tage  — max. Nutzungsdauer in Tagen
--       max_puffer_tage   — max. Versatz Abholung/Rückgabe je Seite in Tagen
-- (B) Tabelle zhl_dauer_ausnahme — begründete Sonderfreigabe-Anfragen mit Token-Grant.
-- (C) Globale Defaults + Mail-Empfänger in zhl_settings (idempotent via INSERT IGNORE).
--
-- Idempotent (MariaDB >= 10.6 / MySQL >= 8): ADD COLUMN IF NOT EXISTS, CREATE TABLE IF NOT EXISTS.

-- (A) Pro-Gerät-Override -----------------------------------------------------------------------
ALTER TABLE zhl_uebergabe
  ADD COLUMN IF NOT EXISTS max_nutzung_tage INT NULL,
  ADD COLUMN IF NOT EXISTS max_puffer_tage  INT NULL;

-- (B) Ausnahme-Anfragen ------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS zhl_dauer_ausnahme (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  user_id               INT NOT NULL,
  resource_id           INT NOT NULL,
  requested_begin_utc   DATETIME NOT NULL,        -- gewünschte Nutzung (kann das Limit überschreiten)
  requested_end_utc     DATETIME NOT NULL,
  nutzung_tage          INT NOT NULL,             -- Snapshot zum Anfragezeitpunkt (informativ)
  puffer_vor_tage       INT NOT NULL DEFAULT 0,
  puffer_nach_tage      INT NOT NULL DEFAULT 0,
  reason                TEXT NOT NULL,            -- Begründung des Nutzers
  status                VARCHAR(16) NOT NULL DEFAULT 'open',  -- open|approved|declined|used|expired
  grant_token           VARCHAR(40) NULL,
  claim_nonce           VARCHAR(40) NULL,         -- für atomares single-winner Claim/Release
  approved_until_utc    DATETIME NULL,            -- Grant-Ablauf
  admin_note            TEXT NULL,
  handled_by            INT NULL,
  handled_at            DATETIME NULL,
  used_at               DATETIME NULL,
  used_reference_number VARCHAR(40) NULL,         -- die letztlich gebuchte Reservierung
  created_at            DATETIME NOT NULL,
  UNIQUE KEY uq_grant_token (grant_token),
  KEY idx_status (status),
  KEY idx_user_resource (user_id, resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- (C) Globale Defaults -------------------------------------------------------------------------
-- ausleih_ausnahme_empfaenger bewusst leer geseedet → fällt im Code auf ZhlTerminRequest::RecipientEmail()
-- (test-sichere Hauspost-Config: Staging = Testadresse, Prod = zhlmedien@…) zurück.
INSERT IGNORE INTO zhl_settings (k, v, updated_at) VALUES
  ('ausleih_max_nutzung_tage',     '14', NOW()),
  ('ausleih_max_puffer_tage',      '5',  NOW()),
  ('ausleih_ausnahme_gueltig_tage','21', NOW());
