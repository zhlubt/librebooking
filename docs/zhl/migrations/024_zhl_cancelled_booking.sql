-- 024: Stornierte Buchungen sichtbar halten (2026-06-27).
--
-- Eine Stornierung löscht die native Reservierung HART (Reservation::Delete) und räumt
-- zhl_booking_handover auf — danach ist die Buchung über ReservationViewRepository nicht
-- mehr auffindbar. Damit der/die Stornierende die eigene stornierte Buchung weiterhin in
-- „Meine Buchungen" unter dem Tab „Storniert" sieht, schreibt der Storno-Handler
-- (ZhlBookingDetailPresenter::HandleCancel) VOR/NACH dem Löschen einen Schnappschuss der
-- Buchungs-Eckdaten hierher. Reine Anzeige-Historie (read-only auf der Buchungsseite).
--
-- Idempotent: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS zhl_cancelled_booking (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  reference_number VARCHAR(48) NOT NULL,
  user_id          MEDIUMINT UNSIGNED NOT NULL,
  title            VARCHAR(255) NOT NULL DEFAULT '',
  resource_names   TEXT NULL,                 -- Gerätenamen, mit "\n" verbunden (nur Anzeige)
  device_count     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  start_utc        DATETIME NULL,
  end_utc          DATETIME NULL,
  cancelled_at     DATETIME NOT NULL,         -- UTC
  INDEX idx_cb_user (user_id),
  INDEX idx_cb_ref (reference_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
