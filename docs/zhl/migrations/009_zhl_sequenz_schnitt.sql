-- ZHL Dashboard v3b-2 — Sequenz Video→Schnitt (US-15).
--
-- Wenn ein Vorhaben Aufnahmen erzeugt, will der Nutzer sie danach schneiden. Bundle-Flag
-- `offer_schnitt`: zeigt im Assistenten eine „Danach schneiden?"-Karte, die den Schnitt-/VR-PC
-- als GETRENNTE Folge-Buchung anbietet (eigener Zeitraum NACH der Drehphase). Bewusst keine
-- technische Verknüpfung der zwei Buchungen, Schnitt-PC ist immer optional (§7.5 / Codex).
--
-- Idempotenz: Spalten-Add via SHOW-COLUMNS-Guard im Apply-Wrapper; Seed nur wo Flag noch 0.

ALTER TABLE zhl_bundle
  ADD COLUMN offer_schnitt TINYINT(1) NOT NULL DEFAULT 0 AFTER einweisung_url;

-- Seed: Video-/Audio-produzierende Bundles bekommen die Schnitt-Folgebuchung angeboten.
-- VR (kein Schnitt) bleibt aus; 360-Grad hat eigene Experten-Beratung (Stitching), bekommt aber
-- ebenfalls die generische Schnitt-Karte als Hilfe.
UPDATE zhl_bundle SET offer_schnitt = 1
  WHERE name LIKE 'Vlog%' OR name LIKE 'Vorlesung%' OR name LIKE 'Imagefilm%'
     OR name LIKE 'Profi-Film%' OR name LIKE 'Podcast%' OR name LIKE 'Videostudio%'
     OR name LIKE 'Drohne%' OR name LIKE '360-Grad%';
