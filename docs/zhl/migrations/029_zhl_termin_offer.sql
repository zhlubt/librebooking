-- 029: Einführungs-Terminwunsch mit Aushandlung (SPEC-EINFUEHRUNG-AUSHANDLUNG, 2026-06-28).
--
-- Erweitert die Wunschtermin-Anfrage (027) zu einem Einführungs-Terminwunsch mit Aushandlung:
-- ein oder mehrere Admins tragen konkrete Termin-ANGEBOTE ein (je mit „wer macht die Einführung"),
-- der Nutzer wählt per login-freiem Token-Link einen aus (= Bestätigung). Bestätigung erzeugt eine
-- .ics-Einladung an Nutzer + Instructor; ein Storno-Link sagt für alle ab (CANCEL-ICS, gleiche UID).
-- Die Geräte-LEIHE bleibt separat (Nutzer bucht selbst ab Einführungs-Ende).
--
-- Ersetzt fachlich den alten 1-Klick-„Als Admin buchen"-Range-Pfad (der eine durchgehende
-- Mehrtages-Reservierung anlegte — beim stundenweisen Studio falsch).
--
-- Idempotent: ADD COLUMN/INDEX IF NOT EXISTS (MariaDB >= 10.6 / MySQL >= 8), CREATE TABLE IF NOT EXISTS.

-- 1) zhl_termin_request additiv erweitern -----------------------------------------------------------
ALTER TABLE zhl_termin_request
  ADD COLUMN IF NOT EXISTS accept_token         VARCHAR(40) NULL,   -- login-freier Auswahl-/Storno-Token
  ADD COLUMN IF NOT EXISTS chosen_offer_id      INT UNSIGNED NULL,  -- gewähltes Angebot (nach Bestätigung)
  ADD COLUMN IF NOT EXISTS confirmed_at         DATETIME NULL,
  ADD COLUMN IF NOT EXISTS einf_reservation_ref VARCHAR(32) NULL;   -- reference_number der nativen Einführungs-Reservierung (§6) für Storno

-- UNIQUE auf accept_token (NULL-tolerant: MySQL/MariaDB erlauben mehrere NULLs).
ALTER TABLE zhl_termin_request
  ADD UNIQUE INDEX IF NOT EXISTS uq_accept_token (accept_token);

-- status-Wertebereich (VARCHAR, kein ENUM): open | offered | confirmed | declined | cancelled | booked(Altdaten)

-- 2) zhl_termin_offer (neu) -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS zhl_termin_offer (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id       INT UNSIGNED NOT NULL,                  -- → zhl_termin_request.id
  instructor_uid   MEDIUMINT UNSIGNED NOT NULL,            -- WER die Einführung macht (ICS-Organizer)
  instructor_name  VARCHAR(190) NOT NULL DEFAULT '',       -- Snapshot „Vorname Nachname"
  created_by_uid   MEDIUMINT UNSIGNED NOT NULL,            -- WELCHER Admin den Termin eintrug (Audit)
  start_utc        DATETIME NOT NULL,                      -- Einführungs-Start (UTC)
  end_utc          DATETIME NOT NULL,                      -- Einführungs-Ende (UTC)
  note             VARCHAR(500) NULL,
  status           VARCHAR(12) NOT NULL DEFAULT 'open',    -- open | chosen | withdrawn
  ics_uid          VARCHAR(80) NULL,                       -- stabile iCalendar-UID (gesetzt bei chosen)
  ics_sequence     SMALLINT UNSIGNED NOT NULL DEFAULT 0,   -- 0=REQUEST, 1=CANCEL
  created_at       DATETIME NOT NULL,
  chosen_at        DATETIME NULL,
  withdrawn_at     DATETIME NULL,
  PRIMARY KEY (id),
  INDEX idx_request_status (request_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
