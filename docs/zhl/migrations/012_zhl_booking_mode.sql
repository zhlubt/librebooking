-- ZHL v-book — Buchungs-Modus je Gerät (Termin-Vorschläge statt Datumsfelder).
--
-- 'day'  = Tagesmodus: der Nutzer wählt aus freien Starttagen (Chips) + Dauer; Buchungszeiten werden
--          an die buchbaren Schedule-Perioden ausgerichtet (sonst lehnt native SchedulePeriodRule ab).
-- 'slot' = Slotmodus: der Nutzer wählt Tag + freien 2-Stunden-Slot (aus den Stunden-Perioden des
--          Schedules gebündelt). Für das Videostudio (Layout = Stunden-Raster 07–21 Uhr).
--
-- Idempotenz: Spalten-Add via SHOW-COLUMNS-Guard im Apply-Wrapper.

ALTER TABLE zhl_uebergabe
  ADD COLUMN booking_mode VARCHAR(8) NOT NULL DEFAULT 'day' AFTER vorlauf_toleranz_h;

UPDATE zhl_uebergabe SET booking_mode = 'slot' WHERE resource_id = 21;  -- Videostudio
