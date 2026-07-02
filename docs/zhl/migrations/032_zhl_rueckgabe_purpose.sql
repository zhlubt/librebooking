-- 032: Rückgabetermin-Anfrage (SPEC-RUECKGABE-ANFRAGE v3, AK-9, 2026-07-02).
--
-- Erweitert die bestehende Termin-Aushandlung (027/029) um einen Zweck-Diskriminator, damit derselbe
-- Flow (Anfrage → Admin bietet Termine an → Nutzer wählt per Token → Mail + .ics an alle Beteiligten)
-- auch für die persönliche RÜCKGABE genutzt werden kann, wenn kein regulärer Übergabe-Slot frei ist.
--
-- BEWUSST reduziert (Paul 2026-07-02): reine Terminkoordination per Kalendereinladung. KEINE
-- Terminplaner-Buchung, KEINE Reservierung/Injektion, KEIN Verfall — daher nur diese eine Spalte.
--
-- Zielplattform ist MariaDB (>= 10.6, media/meet) — dort ist `ADD COLUMN IF NOT EXISTS` gültig und
-- idempotent (wie in den Migrationen 029/031). ACHTUNG: `IF NOT EXISTS` ist auf MySQL 8 KEIN gültiges
-- ALTER-Syntax; dort (oder generell doppellauf-sicher) über den INFORMATION_SCHEMA-Guard unten anwenden.
-- Bestehende Zeilen = 'einf' (Default) → kein Regress für die Einführungs-Aushandlung.

ALTER TABLE zhl_termin_request
  ADD COLUMN IF NOT EXISTS purpose VARCHAR(8) NOT NULL DEFAULT 'einf';  -- 'einf' | 'return'

-- Portabler/doppellauf-sicherer Guard (auch MySQL 8): nur anlegen, wenn die Spalte fehlt.
-- SET @ddl = IF(
--   (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
--     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'zhl_termin_request' AND COLUMN_NAME = 'purpose') = 0,
--   "ALTER TABLE zhl_termin_request ADD COLUMN purpose VARCHAR(8) NOT NULL DEFAULT 'einf'",
--   'SELECT 1');
-- PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
