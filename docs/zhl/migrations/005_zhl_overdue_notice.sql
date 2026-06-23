-- ZHL Übergabe-Modul Phase C: Overdue-/Rückgabe-Eskalation (F34).
-- Protokolliert, welche Eskalationsstufe für eine überfällige Rückgabe schon versandt
-- wurde — verhindert Doppel-Mails und treibt die Stufen voran. Lebt vom Cron-Runner
-- (Web/zhl-cron.php → Jobs/zhl_overdue.php).

CREATE TABLE IF NOT EXISTS zhl_overdue_notice (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  handover_id      INT UNSIGNED NOT NULL,             -- zhl_booking_handover.id (return); NOT NULL → Unique greift (MySQL behandelt NULLs als verschieden)
  handover_token   VARCHAR(64)  NULL,
  reference_number VARCHAR(255) NULL,
  stage            TINYINT UNSIGNED NOT NULL,         -- 1,2,3 … (Eskalationsstufe)
  recipient_email  VARCHAR(255) NULL,
  sent_at          DATETIME NOT NULL,
  UNIQUE KEY uq_handover_stage (handover_id, stage),  -- jede Stufe genau einmal
  INDEX idx_reference (reference_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
