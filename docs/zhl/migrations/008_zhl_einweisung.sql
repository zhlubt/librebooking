-- ZHL Dashboard v3b — Einweisungs-Stufen je Bundle (US-16 / US-20).
--
-- Bisher steckte die Einweisungs-Anforderung als Freitext im `hint`. Hier wird sie zu einem
-- strukturierten, admin-pflegbaren Feld mit drei Stufen erhoben:
--   ① zwingend       — hartes Gate in der UI (z. B. Videostudio, Drohne)
--   ② empfehlenswert — nur Hinweis/Angebot, blockt NICHT (z. B. Podcast)
--   ③ beratung       — Experten-Gespräch „für danach" (z. B. Insta360/360-Grad)
--   + keine          — Default, kein Hinweis
--
-- WICHTIG (Codex §11): Stufe ② geht bewusst NIE ins harte Permission-Gate. Und: dieses
-- Bundle-Feld ist ein TRICHTER-Hinweis im Assistenten — KEIN zertifikatsbasiertes Hart-Blocken
-- der nativen Buchung (das wäre F40-Permission-Logik pro Gerät, späterer Ausbau).
--
-- Idempotenz: Spalten-Adds sind NICHT idempotent — der Apply-Wrapper prüft vorher per SHOW COLUMNS.

-- VARCHAR statt ENUM (Codex): spätere Stufen ohne DDL ergänzbar; gültige Werte werden
-- serverseitig gewhitelistet (ZhlBundlesAdminPresenter::einweisungLevel()).
ALTER TABLE zhl_bundle
  ADD COLUMN einweisung_level VARCHAR(20) NOT NULL DEFAULT 'keine' AFTER hint,
  ADD COLUMN einweisung_text  VARCHAR(500) NULL AFTER einweisung_level,
  ADD COLUMN einweisung_url   VARCHAR(300) NULL AFTER einweisung_text;

-- Seed: bestehende Freitext-Hinweise in Stufen überführen (nur wo noch 'keine', also re-run-sicher).
UPDATE zhl_bundle SET einweisung_level='zwingend',
    einweisung_text='Vor der ersten Nutzung des Videostudios ist eine Einführung zwingend nötig. Zwei Wege: das Monatsseminar „Studio-Einführung" oder ein Einzeltermin mit unserem Team.'
  WHERE name LIKE 'Videostudio%' AND einweisung_level='keine';

UPDATE zhl_bundle SET einweisung_level='zwingend',
    einweisung_text='Für die Drohne ist eine Einweisung zwingend, bevor du sie ausleihen kannst.'
  WHERE name LIKE 'Drohne%' AND einweisung_level='keine';

UPDATE zhl_bundle SET einweisung_level='empfehlenswert',
    einweisung_text='Eine kurze Einweisung ins Podcast-Setup ist empfehlenswert, aber nicht Pflicht.'
  WHERE name LIKE 'Podcast%' AND einweisung_level='keine';

UPDATE zhl_bundle SET einweisung_level='beratung',
    einweisung_text='Sprich vor der Aufnahme kurz mit unserem Experten über die Weiterverarbeitung (Stitching/Schnitt) der 360°-Aufnahmen.'
  WHERE name LIKE '360-Grad%' AND einweisung_level='keine';
