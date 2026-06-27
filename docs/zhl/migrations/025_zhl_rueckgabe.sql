-- 025: Rückgabe-Modus pro Gerät (SPEC-RUECKGABE, 2026-06-27).
--
-- Spiegelbild zu zhl_uebergabe.abholung: steuert, ob beim Buchen ein persönlicher
-- RÜCKGABE-Termin (Terminplaner-Typ „Übergabe Medien", am/nach dem Ausleihende) Pflicht
-- ist und die native Reservierung bis zum Rückgabetag verlängert wird.
--
--   rueckgabe: nicht_noetig | abgeben | abgeben_persoenlich (Termin Pflicht)
--   - abgeben_persoenlich → Pflicht-Rückgabetermin + Reservierung nach hinten (returnApplies()).
--   - abgeben / nicht_noetig → wie heute: return-Zeile auf Ausleihende, kein Picker.
--   rueckgabeort existiert bereits (016) und liefert den Ort per Live-Join (kein Snapshot).
--
-- Idempotent (MariaDB >= 10.6 / MySQL >= 8): ADD COLUMN IF NOT EXISTS.

ALTER TABLE zhl_uebergabe
  ADD COLUMN IF NOT EXISTS rueckgabe VARCHAR(24) NOT NULL DEFAULT 'abgeben';

-- KEIN Seed hier: die Migration läuft in JEDER Umgebung (auch Prod), ein Testgerät-Seed gehört
-- nicht hinein (Codex-Befund 2026-06-27). Das Test-Gerät (Blackmagic Pocket 6K, res 22) wird auf
-- media-Staging gezielt über das Admin-Feld „Rückgabe-Modus" auf „Persönliche Rückgabe" gesetzt
-- (zhl-uebergabe-admin.php) — nicht über die Migration.
