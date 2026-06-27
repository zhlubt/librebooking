-- 024: Wunschtermin-Anfrage (B, 2026-06-27).
--
-- Wenn ein Nutzer für ein Gerät/Bundle keinen passenden Termin buchen kann (kein freier Abhol-/
-- Einführungs-Slot, Gerät bis zum letzten buchbaren Termin verliehen …), kann er statt abzubrechen
-- eine WUNSCHTERMIN-ANFRAGE stellen. Diese reserviert das Gerät NICHT hart (Anfrage-only) — das
-- Medien-Team bekommt eine Mail, stimmt einen Termin ab und trägt die Buchung als Admin ein
-- (umgeht Mindestfristen/Pflicht-Slot). Absagen (Nutzer storniert / Admin lehnt ab) ist möglich.
--
-- Mindestfristen bleiben für den normalen Buchungsweg; nur der Admin darf kurzfristig eintragen.
-- Idempotent: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS zhl_termin_request (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id          MEDIUMINT UNSIGNED NOT NULL,
  kind             VARCHAR(8) NOT NULL DEFAULT 'single',   -- 'single' | 'bundle'
  resource_id      SMALLINT UNSIGNED NULL,                 -- bei kind=single
  bundle_id        INT UNSIGNED NULL,                      -- bei kind=bundle
  label            VARCHAR(190) NOT NULL DEFAULT '',        -- Geräte-/Bundle-Name (Snapshot)
  desired_start    DATETIME NULL,                          -- Wunsch-Zeitraum (UTC)
  desired_end      DATETIME NULL,
  project_title    VARCHAR(190) NOT NULL DEFAULT '',
  message          TEXT NULL,
  status           VARCHAR(12) NOT NULL DEFAULT 'open',     -- open | booked | declined | cancelled
  admin_note       VARCHAR(500) NULL,
  reference_number VARCHAR(32) NULL,                        -- gesetzt, wenn der Admin gebucht hat
  created_at       DATETIME NOT NULL,
  handled_at       DATETIME NULL,
  handled_by       MEDIUMINT UNSIGNED NULL,
  PRIMARY KEY (id),
  INDEX idx_status (status),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
