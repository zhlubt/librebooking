-- 021: Shure & Yeti als eigene Geräte-Typen + Auto-Prio im Bundle „Podcast aufnehmen" (Weg 2, 2026-06-26).
--
-- Bisher waren beide Podcast-Mikrofone derselbe Typ „Podcast-Mikrofon", eine Priorisierung
-- Shure-vor-Yeti war damit unmöglich. Jetzt:
--   rid 28 (Podcast Bundle mit 2x Shure SM7B …) → Typ „Podcast-Mikrofon (Shure)"
--   rid 29 (Blue Yeti USB-Podcastmikrofon)      → Typ „Podcast-Mikrofon (Yeti)"
-- und Bundle #6 bekommt eine Alternativ-Gruppe „podcastmic" im Auto-Modus (alt_mode='auto'):
-- der Resolver nimmt automatisch die erste freie Option nach sort_order → Shure (0), sonst Yeti (1).
--
-- aid 15 = Attribut „Geräte-Typ" (custom_attributes), attribute_category 4 = RESOURCE.
-- Idempotent: UPDATEs sind wertsetzend; das INSERT prüft per NOT EXISTS auf die Yeti-Option.

-- 1) Ressourcen umtypisieren ------------------------------------------------
-- Guard auf den Alt-Wert: ein erneuter Lauf nach späterer manueller Korrektur überschreibt nichts.
UPDATE custom_attribute_values
   SET attribute_value = 'Podcast-Mikrofon (Shure)'
 WHERE custom_attribute_id = 15 AND attribute_category = 4 AND entity_id = 28
   AND attribute_value = 'Podcast-Mikrofon';

UPDATE custom_attribute_values
   SET attribute_value = 'Podcast-Mikrofon (Yeti)'
 WHERE custom_attribute_id = 15 AND attribute_category = 4 AND entity_id = 29
   AND attribute_value = 'Podcast-Mikrofon';

-- 2) Bundle #6: bestehendes Mikro-Item zur Shure-Option der Auto-Gruppe machen
UPDATE zhl_bundle_item
   SET type_label = 'Podcast-Mikrofon (Shure)',
       alt_group  = 'podcastmic',
       alt_mode   = 'auto',
       required   = 1,
       quantity   = 1,
       sort_order = 0
 WHERE id = 37 AND bundle_id = 6;

-- 3) Bundle #6: Yeti als zweite (nachrangige) Option der Auto-Gruppe ergänzen
INSERT INTO zhl_bundle_item
  (bundle_id, type_label, quantity, required, alt_group, alt_mode, phase, sort_order, note)
SELECT 6, 'Podcast-Mikrofon (Yeti)', 1, 1, 'podcastmic', 'auto', 'main', 1, ''
 WHERE NOT EXISTS (
   SELECT 1 FROM zhl_bundle_item
    WHERE bundle_id = 6 AND alt_group = 'podcastmic' AND type_label = 'Podcast-Mikrofon (Yeti)'
 );
