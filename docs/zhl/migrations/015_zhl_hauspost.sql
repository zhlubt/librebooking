-- ZHL C2 — Hauspost für analoge Medien (2026-06-24).
--
-- Analoge Medien (z. B. Flipcharts, Moderationskoffer; schedule_id 6 „Analoge Moderation")
-- können statt persönlicher Abholung per interner Hauspost verschickt werden. Wählt der
-- Ausleihende „Hauspost", füllt er ein Zusatzformular (Felder a–f2); beim Absenden wird ein
-- Antrag mit Status 'pending_review' gespeichert und der Transport-Organisator + das Medien-
-- Team + der Ausleihende per E-Mail informiert.
--
-- Codex-Entscheidung: Hauspost ist ein EIGENER Fulfillment-Pfad — KEINE fingierten
-- Terminplaner-Abholtermine. Daher kein zhl_booking_handover-Eintrag, sondern die neue
-- Tabelle zhl_hauspost_request.
--
-- Idempotent (MariaDB 10.6+: ADD COLUMN IF NOT EXISTS / CREATE TABLE IF NOT EXISTS).

-- 1. Pro-Gerät-Flag: dieses (analoge) Medium darf per Hauspost verschickt werden.
ALTER TABLE zhl_uebergabe
  ADD COLUMN IF NOT EXISTS hauspost_allowed TINYINT(1) NOT NULL DEFAULT 0;

-- 2. Hauspost-Anträge.
--    reference_number wird NACH dem Anlegen der nativen Reservierung nachgetragen
--    (analog zur C1-Abhol-Logik). einsatz_termin bleibt Freitext (VARCHAR), da der
--    Einsatz auch ein Zeitraum sein kann; die Vorlauf-Prüfung versucht best-effort ein
--    Datum daraus zu parsen (siehe Presenter).
CREATE TABLE IF NOT EXISTS zhl_hauspost_request (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  reference_number  VARCHAR(255) NULL,
  resource_id       SMALLINT UNSIGNED NULL,
  user_id           MEDIUMINT UNSIGNED NOT NULL,
  einsatz_termin    VARCHAR(120) NULL,   -- a) Datum/Zeit des Einsatzes (Freitext, ggf. Zeitraum)
  einsatz_raum      VARCHAR(190) NULL,   -- b) Raum des Einsatzorts
  einsatz_titel     VARCHAR(190) NULL,   -- c) Titel/Name des Einsatzes
  liefer_fenster    VARCHAR(190) NULL,   -- d) Wann kann angeliefert werden?
  rueckhol_fenster  VARCHAR(190) NULL,   -- e) Wann kann wieder abgeholt werden?
  anlieferort       VARCHAR(190) NULL,   -- f1) eindeutige Bezeichnung des Anlieferungsorts
  abholort_hauspost VARCHAR(190) NULL,   -- f2) eindeutige Bezeichnung des Abholungsorts
  status            VARCHAR(16) NOT NULL DEFAULT 'pending_review',
  created_at        DATETIME NOT NULL,
  INDEX idx_ref (reference_number),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Seed: Hauspost für die analogen Moderations-Medien freischalten (schedule_id 6).
--    Schritt 3a legt zuerst für schedule-6-Ressourcen OHNE zhl_uebergabe-Zeile eine
--    Default-Zeile an (abholung='abholen' = Default), damit das Flag überhaupt gesetzt
--    werden kann und der Hauspost-Pfad angeboten wird. Das ist sicher, weil 'abholen'
--    ohnehin der Lookup-Default ist (lookupUebergabe()), wenn keine Zeile existiert —
--    wir materialisieren also nur den bestehenden Default.
INSERT INTO zhl_uebergabe (resource_id, abholung, einfuehrung, updated_at)
SELECT r.resource_id, 'abholen', 'keine', UTC_TIMESTAMP()
FROM resources r
WHERE r.schedule_id = 6
  AND NOT EXISTS (SELECT 1 FROM zhl_uebergabe u WHERE u.resource_id = r.resource_id);

-- 3b. Flag für alle analogen Moderations-Medien setzen.
UPDATE zhl_uebergabe u
JOIN resources r ON r.resource_id = u.resource_id
SET u.hauspost_allowed = 1
WHERE r.schedule_id = 6;
