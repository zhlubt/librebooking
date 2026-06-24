-- ZHL v-cert — Einführungs-Bestätigung (Einweiser bestätigt → Zertifikat).
--
-- Nach einer gebuchten Einführung wird eine offene Bestätigung angelegt. Der Einweiser erhält (falls am
-- Zertifikatstyp eine `confirm_email` hinterlegt ist) per Mail einen token-Link; mit Klick auf „Ja"
-- (Web/zhl-cert-confirm.php) wird dem Nutzer der passende Zertifikatstyp vergeben (zhl_cert_grant +
-- Projektion auf zhl_certificate). Token = Zugriffsschutz (kein Login nötig).

CREATE TABLE IF NOT EXISTS zhl_cert_confirmation (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  token        VARCHAR(64) NOT NULL UNIQUE,
  user_id      MEDIUMINT UNSIGNED NOT NULL,
  cert_type_id INT UNSIGNED NOT NULL,
  resource_id  SMALLINT UNSIGNED NULL,
  status       VARCHAR(12) NOT NULL DEFAULT 'pending',   -- pending|confirmed|rejected
  created_at   DATETIME NOT NULL,
  confirmed_at DATETIME NULL,
  INDEX idx_status (status), INDEX idx_user (user_id),
  CONSTRAINT fk_conf_type FOREIGN KEY (cert_type_id) REFERENCES zhl_cert_type (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bestätigungs-Mailadresse (Einweiser) je Zertifikatstyp.
ALTER TABLE zhl_cert_type ADD COLUMN confirm_email VARCHAR(190) NULL;
