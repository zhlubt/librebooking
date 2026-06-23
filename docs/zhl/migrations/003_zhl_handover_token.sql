-- ZHL Übergabe-Modul — Token-Ownership (Auth-Bindung, Codex-Finding #1).
-- Bindet ein handover_token an den LibreBooking-User, der es erzeugt hat. Damit
-- kann der Übergabe-Assistent (zhl-handover-select.php) den Status/Sync auf den
-- Eigentümer beschränken, und das PreReservation-Gate prüft, dass das im
-- Reservierungs-Attribut eingetragene Token dem buchenden User gehört.

CREATE TABLE IF NOT EXISTS zhl_handover_token (
  handover_token   VARCHAR(64) NOT NULL PRIMARY KEY,
  user_id          MEDIUMINT UNSIGNED NOT NULL,
  reference_number VARCHAR(255) NULL,
  created_at       DATETIME NOT NULL,
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
