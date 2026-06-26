-- ZHL Rückgabeort + Storno-Status (Block D, Nutzer/Codex 2026-06-24/25).
--
-- D1 (Medienmanager-Tagesseite): Default-RÜCKGABEORT pro Gerät, damit der
-- Medienmanager die fälligen Rückgaben des Tages nach Ort gruppiert sieht
-- (z. B. „im Cateringschrank", „im Videostudio"). Pendant zu zhl_uebergabe.abholort.
-- Codex-Entscheidung: pro Gerät fix (Default), beim Anlegen der return-Zeile als
-- Snapshot übernommen; Staff darf überschreiben — NICHT vom Ausleihenden frei wählbar.
--
-- D2-Vorbereitung (harmlos jetzt): Status-ENUM um 'cancelled' erweitern, damit das
-- spätere Re-Ausleih-Gate stornierte/native-Storno-Übergaben schließen kann, ohne
-- ein Gerät dauerhaft zu sperren.
--
-- Idempotent (MariaDB >= 10.6 / MySQL >= 8): ADD COLUMN IF NOT EXISTS + MODIFY.

-- D1: Default-Rückgabeort pro Gerät.
ALTER TABLE zhl_uebergabe
  ADD COLUMN IF NOT EXISTS rueckgabeort VARCHAR(200) NULL;

-- D2-Prep: 'cancelled' zum Status-ENUM hinzufügen (idempotent: MODIFY setzt die
-- Spalte hart auf diese Definition; Bestandsdaten bleiben, da ihre Werte enthalten sind).
ALTER TABLE zhl_booking_handover
  MODIFY COLUMN status ENUM('requested','confirmed','done','cancelled')
  NOT NULL DEFAULT 'requested';

-- Optionaler, MINIMALER Seed eines sinnvollen Default-Rückgabeorts für die
-- Geräte des Ausleih-Schedules (schedule_id=5). Bewusst auskommentiert — die
-- konkreten Orte setzt der Medienmanager; nur als Vorlage hier hinterlegt.
--
-- INSERT INTO zhl_uebergabe (resource_id, rueckgabeort, updated_at)
--   SELECT r.resource_id, 'ZHL Medienraum 4.2.10', UTC_TIMESTAMP()
--   FROM resources r
--   WHERE r.schedule_id = 5
-- ON DUPLICATE KEY UPDATE
--   rueckgabeort = COALESCE(zhl_uebergabe.rueckgabeort, VALUES(rueckgabeort)),
--   updated_at   = VALUES(updated_at);
