-- 020: Alt-Gruppen-Modus für Prioritäts-Fallback (C, 2026-06-26).
--
-- zhl_bundle_item.alt_mode steuert, wie eine Alternativ-Gruppe (alt_group) aufgelöst wird:
--   'choice' (Default, bisheriges Verhalten) = der Nutzer wählt eine Option (Radio).
--   'auto'   = automatische PRIORITÄT: der Resolver nimmt die erste Option (nach sort_order)
--              mit genug freien Geräten. Beispiel Podcast-Mikro: Shure → sonst Yeti → sonst Funkmik.
--
-- Idempotent (MariaDB: ADD COLUMN IF NOT EXISTS).

ALTER TABLE zhl_bundle_item
  ADD COLUMN IF NOT EXISTS alt_mode VARCHAR(8) NOT NULL DEFAULT 'choice';
