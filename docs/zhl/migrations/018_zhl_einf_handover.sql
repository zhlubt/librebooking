-- ZHL — Einführungstermin lokal mitschreiben (2026-06-25).
--
-- Bisher wurde der Einführungstermin nur im Terminplaner (meet) gebucht; lokal entstand
-- nur eine offene zhl_cert_confirmation (ohne Zeitpunkt). Die Abholung dagegen liegt mit
-- Datum in zhl_booking_handover. Damit die Buchungs-Detailseite den Einführungstermin
-- JE GERÄT mit Datum zeigen kann, wird er ebenfalls in zhl_booking_handover gespeichert —
-- als dritter type-Wert 'einf'. Pro Gerät eine Zeile (eigener handover_token), verknüpft
-- über reference_number + resource_id.
--
-- Idempotent: MODIFY setzt das ENUM auf den Zielzustand (mehrfaches Ausführen unschädlich).

ALTER TABLE zhl_booking_handover
  MODIFY COLUMN type ENUM('pickup','return','einf') NOT NULL;
