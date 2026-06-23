-- ZHL Übergabe-Modul Phase B: Checkliste/Zustand (F10/F30).
-- Erweitert die in 002 angelegten Protokoll-Tabellen, damit auch ad-hoc-Positionen
-- (ohne strukturiertes Accessory) und der Gesamtzustand erfasst werden können.

-- Name/Bezeichnung der Position. Für strukturierte Accessories ein Snapshot des
-- Namens (audit-fest, auch wenn das Accessory später gelöscht wird); für ad-hoc-
-- Positionen die freie Bezeichnung.
ALTER TABLE zhl_handover_check_item
  ADD COLUMN label VARCHAR(255) NULL AFTER accessory_id;

-- Gesamtzustand des Geräts bei der Übergabe (F30).
ALTER TABLE zhl_handover_check
  ADD COLUMN overall_condition ENUM('ok','minor','damaged') NULL AFTER type;
