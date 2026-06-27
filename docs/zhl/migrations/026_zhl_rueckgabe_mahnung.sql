-- 026: Rückgabe-Erinnerungen/Mahnungen mit Freigabe-Gate (Mail-Umsetzung, 2026-06-27).
--
-- Ersetzt die rein automatische Overdue-Eskalation. Drei Stufen je Rückgabe (zhl_booking_handover,
-- type='return'):
--   stage 1 = Vortag-Erinnerung (freundlich) — geht AUTOMATISCH raus (1 Tag vor Rückgabe).
--   stage 2 = überfällig, deutlich        — wird vom Job nur als 'pending' eingereiht (NICHT gesendet).
--   stage 3 = überfällig, letzte Erinnerung — ebenfalls 'pending'.
-- Die überfälligen Stufen gibt ein Admin EINZELN frei (Gerät könnte längst zurück sein, nur unbestätigt).
--
-- UNIQUE(handover_id, stage) = Dedup (eine Stufe je Rückgabe nur einmal). Snapshot der Empfänger-/
-- Geräte-Daten, damit Versand/Anzeige unabhängig von späteren Änderungen sind.
-- Idempotent: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS zhl_rueckgabe_mahnung (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  handover_id       INT UNSIGNED NOT NULL,
  stage             TINYINT UNSIGNED NOT NULL,            -- 1=Vortag, 2=deutlich, 3=letzte
  reference_number  VARCHAR(32) NULL,
  resource_name     VARCHAR(190) NOT NULL DEFAULT '',
  recipient_email   VARCHAR(190) NOT NULL DEFAULT '',
  recipient_name    VARCHAR(190) NOT NULL DEFAULT '',
  recipient_user_id MEDIUMINT UNSIGNED NULL,
  language          VARCHAR(10) NULL,
  due_utc           DATETIME NULL,                        -- geplantes Rückgabe-Ende (UTC)
  status            VARCHAR(10) NOT NULL DEFAULT 'pending', -- pending | sent | dismissed
  created_at        DATETIME NOT NULL,
  sent_at           DATETIME NULL,
  handled_by        MEDIUMINT UNSIGNED NULL,
  send_nonce        VARCHAR(32) NULL,                       -- atomarer Freigabe-Claim (gegen Doppelversand)
  PRIMARY KEY (id),
  UNIQUE KEY uq_handover_stage (handover_id, stage),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
