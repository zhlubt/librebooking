-- ZHL Bundle-Buchung (SPEC-BUNDLE-BOOKING) — erweitert zhl_bundle_item um die Felder, die der
-- Resolver + die Buchungs-Seite (Web/zhl-bundle-book.php) brauchen, und baut die Komposition des
-- Bundles id=4 „Profi-Film (BM Pocket 6K)" auf die echte Geräte-Komposition um.
--
-- Idempotenz: MariaDB >= 10.6 → `ADD COLUMN IF NOT EXISTS` (re-run-sicher). Der Seed räumt die
-- Items des Bundles 4 vor dem Neuaufbau ab und setzt sie deterministisch neu — damit ist auch der
-- Seed re-run-sicher (kein doppeltes Anlegen).
--
-- Spalten:
--   alt_group VARCHAR(40) NULL  — Items mit gleichem (non-null) Wert im selben Bundle = Alternativen
--                                  („Stativ ODER Gimbal"); der Nutzer wählt genau eine Option.
--   phase VARCHAR(8) NOT NULL DEFAULT 'main' — 'main' = Aufnahme-Zeitraum, 'after' = Folge-Phase
--                                  (Schnitt-/VR-PC), eigener Zeitraum NACH dem Aufnahme-Ende.
--   meta VARCHAR(255) NULL       — Packlisten-/Maß-Info (Transportkiste, Ladegeräte).
--   specific_resource_id SMALLINT UNSIGNED NULL — KONKRETES Gerät statt Typ-Auflösung (z. B. genau
--                                  die eine Pocket 6K, res 22). NULL = über type_label auflösen.
--
-- Packlisten-Konvention (Codex-Regel: KEINE synthetischen Ressourcen-IDs):
--   quantity = 0  →  reine Packlisten-/Info-Position. Der Resolver bucht sie NICHT und sie blockt
--                    die Verfügbarkeit NICHT. `meta` hält die Anzeige-Info (Maße / Zubehörhinweis).
--                    type_label dient nur als Anzeige-Label.

ALTER TABLE zhl_bundle_item
  ADD COLUMN IF NOT EXISTS alt_group VARCHAR(40) NULL AFTER required,
  ADD COLUMN IF NOT EXISTS phase VARCHAR(8) NOT NULL DEFAULT 'main' AFTER alt_group,
  ADD COLUMN IF NOT EXISTS meta VARCHAR(255) NULL AFTER phase,
  ADD COLUMN IF NOT EXISTS specific_resource_id SMALLINT UNSIGNED NULL AFTER meta;

-- ---------------------------------------------------------------------------------------------
-- Seed: Bundle 4 „Profi-Film (BM Pocket 6K)" — echte Komposition.
-- Nur ausführen, wenn das Bundle existiert (guard via INSERT ... SELECT WHERE EXISTS-Muster).
-- Re-run-sicher: zuerst alle Items des Bundles 4 löschen, dann deterministisch neu anlegen.
-- ---------------------------------------------------------------------------------------------

-- Alte Komposition des Bundles 4 entfernen (nur dieses Bundle; CASCADE betrifft nichts weiter).
DELETE FROM zhl_bundle_item WHERE bundle_id = 4;

-- Kamera: genau die Pocket 6K (res 22), Pflicht, Aufnahme-Phase.
INSERT INTO zhl_bundle_item (bundle_id, type_label, quantity, required, alt_group, phase, meta, specific_resource_id, note, sort_order)
SELECT 4, 'Kamera', 1, 1, NULL, 'main', NULL, 22, 'Blackmagic Pocket Cinema 6K', 10
WHERE EXISTS (SELECT 1 FROM zhl_bundle WHERE id = 4);

-- Objektiv: Typ „Objektiv", 1 Stück, Pflicht, Aufnahme-Phase (über Typ aufgelöst: res 62/63/64).
INSERT INTO zhl_bundle_item (bundle_id, type_label, quantity, required, alt_group, phase, meta, specific_resource_id, note, sort_order)
SELECT 4, 'Objektiv', 1, 1, NULL, 'main', NULL, NULL, NULL, 20
WHERE EXISTS (SELECT 1 FROM zhl_bundle WHERE id = 4);

-- Halterung: Stativ ODER Gimbal — gleiche alt_group, beide required (genau eine Option wählen).
INSERT INTO zhl_bundle_item (bundle_id, type_label, quantity, required, alt_group, phase, meta, specific_resource_id, note, sort_order)
SELECT 4, 'Stativ', 1, 1, 'halterung', 'main', NULL, NULL, NULL, 30
WHERE EXISTS (SELECT 1 FROM zhl_bundle WHERE id = 4);

INSERT INTO zhl_bundle_item (bundle_id, type_label, quantity, required, alt_group, phase, meta, specific_resource_id, note, sort_order)
SELECT 4, 'Gimbal', 1, 1, 'halterung', 'main', NULL, NULL, NULL, 31
WHERE EXISTS (SELECT 1 FROM zhl_bundle WHERE id = 4);

-- Funkmikrofon: Typ „Funkmikrofon", 1 Stück, Aufnahme-Phase (über Typ aufgelöst: res 45/46/47/...).
INSERT INTO zhl_bundle_item (bundle_id, type_label, quantity, required, alt_group, phase, meta, specific_resource_id, note, sort_order)
SELECT 4, 'Funkmikrofon', 1, 1, NULL, 'main', NULL, NULL, NULL, 40
WHERE EXISTS (SELECT 1 FROM zhl_bundle WHERE id = 4);

-- Transportkiste: KEINE LB-Ressource → Packliste (quantity 0). Maße als Info (Platzhalter).
INSERT INTO zhl_bundle_item (bundle_id, type_label, quantity, required, alt_group, phase, meta, specific_resource_id, note, sort_order)
SELECT 4, 'Transportkiste', 0, 0, NULL, 'main', 'Maße: 85×45×40 cm (Platzhalter — echte Maße eintragen)', NULL, NULL, 50
WHERE EXISTS (SELECT 1 FROM zhl_bundle WHERE id = 4);

-- Ladegeräte/Akkus: KEINE LB-Ressource → Packliste (quantity 0).
INSERT INTO zhl_bundle_item (bundle_id, type_label, quantity, required, alt_group, phase, meta, specific_resource_id, note, sort_order)
SELECT 4, 'Ladegeräte', 0, 0, NULL, 'main', 'Ladegeräte + Akkus inkl.', NULL, NULL, 51
WHERE EXISTS (SELECT 1 FROM zhl_bundle WHERE id = 4);

-- Schnitt-/VR-PC: Folge-Phase (phase='after'), optional (required=0). Über Typ aufgelöst.
INSERT INTO zhl_bundle_item (bundle_id, type_label, quantity, required, alt_group, phase, meta, specific_resource_id, note, sort_order)
SELECT 4, 'Schnitt-/VR-PC', 1, 0, NULL, 'after', NULL, NULL, NULL, 60
WHERE EXISTS (SELECT 1 FROM zhl_bundle WHERE id = 4);
