-- F40 Stufe 2: Zertifikat-Lifecycle (cron-frei, via Permission-Plugin geprüft).
-- ZHL-eigene Tabellen (zhl_-Präfix); kein Eingriff in LibreBooking-Schema.

CREATE TABLE IF NOT EXISTS zhl_certificate_required (
  resource_id SMALLINT UNSIGNED NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS zhl_certificate (
  user_id     MEDIUMINT UNSIGNED NOT NULL,
  resource_id SMALLINT  UNSIGNED NOT NULL,
  granted_at  DATETIME NOT NULL,
  expires_at  DATETIME NULL,            -- NULL = unbegrenzt gültig
  PRIMARY KEY (user_id, resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
