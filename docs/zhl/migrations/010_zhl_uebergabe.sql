-- ZHL Dashboard v-book-3 — Übergabe-Konfiguration PRO GERÄT (Nutzer 2026-06-24).
--
-- Abholung und Einführung sind getrennt und je Gerät unterschiedlich (Typ-Ebene reicht NICHT:
-- z. B. liegen Blackmagic Pocket 6K und Sony ZV1 beide unter Typ „Kamera", brauchen aber
-- unterschiedliche Regeln). Daher Schlüssel = resource_id.
--
--   abholung: nicht_noetig | abholen | abholen_persoenlich (dringend persönlich)
--   abholort: Freitext, vom Medienmanager gesetzt (z. B. „Raum 4.2.10", „vor Mensa", „Cateringwagen")
--   einfuehrung: keine | moeglich | notwendig
--   einfuehrung_typ: Label des Terminplaner-Termintyps (z. B. „Einführung ins Videostudio",
--                    „Einführung in Medien") — Anbindung an meet.zhl-ubt.de folgt (Tool-Rework).
--   tp_member_id: Terminplaner-Member, der die Einweisungs-Termine anbietet (NULL = globaler Default);
--                 aktuell Paul Dölle = 2; später mehrere/priorisierbar.
--   vorlauf_toleranz_h: erlaubte Unterschreitung der nativen Vorlaufzeit für den Übergabe-/Einweisungs-
--                       termin, in Stunden (z. B. 48 → statt 7 Tage dürfen es 5 sein, wenn ein Slot frei ist).

CREATE TABLE IF NOT EXISTS zhl_uebergabe (
  resource_id        SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
  abholung           VARCHAR(24)  NOT NULL DEFAULT 'abholen',
  abholort           VARCHAR(200) NULL,
  einfuehrung        VARCHAR(16)  NOT NULL DEFAULT 'keine',
  einfuehrung_typ    VARCHAR(120) NULL,
  tp_member_id       INT UNSIGNED NULL,
  vorlauf_toleranz_h SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at         DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed der 3 Beispiel-Geräte (idempotent via INSERT … ON DUPLICATE KEY UPDATE im Apply-Wrapper).
-- res 21 Videostudio: keine Abholung (Schlüsseltresor), Einführung zwingend.
-- res 22 Blackmagic Pocket 6K: abholen dringend persönlich, Einführung möglich.
-- Funkmikrofone (res 45,46,47,52,57,58,59,60,61): abholen, Einführung möglich.
