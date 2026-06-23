-- ZHL Übergabe-Modul Phase A: strukturierte Abhol-/Rückgabe-Termine statt Freitext.
-- ZHL-eigene Tabellen (zhl_-Präfix); kein Eingriff ins LibreBooking-Schema.
-- Slots liefert terminplaner_ubt (Google-iCal); hier wird nur die Verknüpfung +
-- das Übergabeprotokoll gespeichert.
--
-- Join-Schlüssel ist das handover_token (löst Henne-Ei: Reservierung braucht eine
-- terminierte Übergabe, die Übergabe wird aber vor dem Speichern der Reservierung
-- gewählt). Pickup + Return sind zwei Zeilen unter demselben Token. reference_number
-- wird vom PostReservation-Plugin nachgetragen, sobald die Reservierung existiert.

CREATE TABLE IF NOT EXISTS zhl_booking_handover (
  id                      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  handover_token          VARCHAR(64)  NOT NULL,
  type                    ENUM('pickup','return') NOT NULL,
  reference_number        VARCHAR(255) NULL,                 -- LB-Reservierung (PostReservation)
  series_id               INT UNSIGNED NULL,
  reservation_instance_id INT UNSIGNED NULL,
  resource_id             SMALLINT UNSIGNED NULL,            -- NULL = gilt für ganze Reservierung
  terminplaner_booking_id VARCHAR(20)  NULL,                 -- terminplaner_ubt.bookings.id
  staff_member_id         INT UNSIGNED NULL,                 -- terminplaner team_members.id
  staff_role              ENUM('primary','backup') NULL,     -- Hilfskraft=primary, ZHL-Team=backup
  scheduled_start_utc     DATETIME NULL,
  scheduled_end_utc       DATETIME NULL,
  status                  ENUM('requested','confirmed','done') NOT NULL DEFAULT 'requested',
  created_at              DATETIME NOT NULL,
  updated_at              DATETIME NOT NULL,
  -- Bewusst PRO VORGANG/RESERVIERUNG (Kapazität 1, ZHL-Entscheidung), NICHT pro
  -- Ressource/Instanz: eine Übergabe = ein Termin (der terminplaner-Kalender erzwingt das).
  -- Für künftige Multi-Resource-/Instanz-genaue Übergaben würde der Constraint um
  -- resource_id/reservation_instance_id erweitert (Phase B/C).
  UNIQUE KEY uq_token_type (handover_token, type),           -- 1 Abholung + 1 Rückgabe je Vorgang
  INDEX idx_reference (reference_number),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Übergabeprotokoll (Phase B nutzt es voll; Tabelle hier schon angelegt).
CREATE TABLE IF NOT EXISTS zhl_handover_check (
  id                      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  handover_token          VARCHAR(64)  NULL,
  reference_number        VARCHAR(255) NULL,
  reservation_instance_id INT UNSIGNED NULL,
  resource_id             SMALLINT UNSIGNED NULL,
  type                    ENUM('pickup','return') NOT NULL,
  checked_by_user_id      MEDIUMINT UNSIGNED NULL,
  condition_note          TEXT NULL,
  signature_name          VARCHAR(255) NULL,
  created_at              DATETIME NOT NULL,
  INDEX idx_reference (reference_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Checklisten-Items NORMALISIERT (Audit/Reporting statt JSON-Blob).
CREATE TABLE IF NOT EXISTS zhl_handover_check_item (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  check_id     INT UNSIGNED NOT NULL,
  accessory_id MEDIUMINT UNSIGNED NULL,
  state        ENUM('ok','missing','damaged') NOT NULL DEFAULT 'ok',
  note         VARCHAR(500) NULL,
  INDEX idx_check (check_id),
  CONSTRAINT fk_check_item FOREIGN KEY (check_id)
    REFERENCES zhl_handover_check(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
