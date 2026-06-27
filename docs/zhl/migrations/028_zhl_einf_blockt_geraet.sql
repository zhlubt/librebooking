-- 028: Einführung reserviert das Gerät selbst (SPEC-STUDIO-EINFUEHRUNG / SPEC-LOAN-RASTER)
--
-- Ersetzt den bisherigen Proxy `booking_mode='slot'` durch ein explizites Flag. Ist es gesetzt,
-- legt die Buchung zusätzlich zur Terminplaner-Einführung eine native 60-Min-Geräte-Reservierung
-- für die Einführungs-Stunde an (Gerät muss vor Ort & frei sein) und bietet im Picker nur Termine
-- an, in denen das Gerät frei ist.
--
-- Idempotenz: Spalten-Add NICHT idempotent → der Apply-Wrapper prüft vorher per SHOW COLUMNS.

ALTER TABLE zhl_uebergabe
  ADD COLUMN einf_blockt_geraet TINYINT(1) NOT NULL DEFAULT 0 AFTER booking_mode;

-- (A) JETZT: Videostudio (res 21). Ersetzt 1:1 den alten booking_mode='slot'-Proxy → Verhalten
--     unverändert, nur explizit gesteuert. res 21 bleibt booking_mode='slot'.
UPDATE zhl_uebergabe SET einf_blockt_geraet = 1 WHERE resource_id = 21;

-- (B) NACH der Schedule-Umstellung auf stündlich (029) + Staging-Test: Verleih-Geräte mit
--     PFLICHT-Einführung. ERST freischalten, wenn deren Schedule stündlich ist UND der Terminplaner-Typ
--     „Einführung in Medien" auf 60 Min / volle Stunde steht — sonst scheitert die 60-Min-Reservierung
--     (Periodengrenze) und der Nutzer bekommt nur einen Warnhinweis.
--     (Pflicht-Einführung laut 017: 22,28,68,53,54,55,56,15,51.)
-- UPDATE zhl_uebergabe SET einf_blockt_geraet = 1
--   WHERE resource_id IN (22,28,68,53,54,55,56,15,51);
