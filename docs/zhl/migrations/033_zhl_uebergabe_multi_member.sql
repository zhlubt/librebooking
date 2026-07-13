-- 033: Mehrere Terminplaner-Anbieter für Einführung/Übergabe (Nutzer-Vorgabe 2026-07-13).
--
-- Migration 017 hat tp_member_id für alle betroffenen Geräte hart auf 2 (Paul Dölle) gesetzt. Das
-- schränkte die Terminplaner-Abfrage (api/lesson_slots.php) je Gerät auf GENAU dieses eine Team-
-- Mitglied ein — obwohl der Endpunkt selbst schon mehrere Anbieter je Termintyp zurückgeben kann
-- (siehe [[zhl-terminplaner-handover-integration]]). Mittlerweile bieten weitere Personen auf
-- meet.zhl-ubt.de ebenfalls "Einführung in Medien" und "Übergabe Medien" an.
--
-- tp_member_id war laut Tabellenkommentar (010_zhl_uebergabe.sql) von Anfang an so gedacht:
-- "NULL = globaler Default; aktuell Paul Dölle = 2; später mehrere/priorisierbar." NULL setzen
-- entfernt die member_id-Einschränkung in ZhlBookPresenter::fetch{Einfuehrung,Handover,Return}Slots
-- (Presenters/ZhlBookPresenter.php, Presenters/ZhlBundleBookPresenter.php) — der Terminplaner liefert
-- dann ALLE aktiven Anbieter des jeweiligen Termintyps (primary zuerst), keine Verhaltensänderung für
-- Termintypen mit weiterhin nur einem Anbieter.
--
-- Zielplattform MariaDB (media.zhl-ubt.de). Idempotent (setzt einfach den Wert, kein Schema-Change).

UPDATE zhl_uebergabe SET tp_member_id = NULL WHERE tp_member_id IS NOT NULL;
