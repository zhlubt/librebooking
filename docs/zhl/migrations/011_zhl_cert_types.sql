-- ZHL v-cert — Benanntes Zertifikat-System (Nutzer 2026-06-24).
--
-- Bisher: zhl_certificate(user_id, resource_id) pro Gerät + natives F40-Plugin (ZhlCertificate,
-- PreReservation) + zhl_certificate_required. Nutzer-Wunsch: benannte Zertifikate je Material
-- („Einführung in Videostudio/Pocket 6K/…") + pflegbare Liste „welches Medium deckt welches Zertifikat ab"
-- + Admin-Zuweisung an Nutzer. Wer das Zertifikat hat, bucht das Material ohne Einführungstermin
-- (Abholung bleibt nötig).
--
-- ARCHITEKTUR: Das benannte System ist die VERWALTUNGS-Wahrheitsquelle. Es wird auf die bestehende
-- `zhl_certificate`-Tabelle PROJIZIERT (rebuild aus zhl_cert_grant × zhl_cert_type_resource), damit
-- BEIDE bestehenden Gates unverändert weiterlesen: (a) mein Einführungs-Gate (zhl-book.php,
-- userIsCertified) und (b) das native F40-Plugin. Kein Gate-Code muss geändert werden.

CREATE TABLE IF NOT EXISTS zhl_cert_type (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150) NOT NULL,                 -- z. B. „Einführung ins Videostudio"
  active      TINYINT(1) NOT NULL DEFAULT 1,
  sort_order  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pflegbare Liste: welches Medium deckt dieses Zertifikat ab (N:M Zertifikat↔Gerät).
CREATE TABLE IF NOT EXISTS zhl_cert_type_resource (
  cert_type_id INT UNSIGNED NOT NULL,
  resource_id  SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (cert_type_id, resource_id),
  INDEX idx_res (resource_id),
  CONSTRAINT fk_ctr_type FOREIGN KEY (cert_type_id) REFERENCES zhl_cert_type (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nutzer hält ein Zertifikat (Admin-Zuweisung, optionaler Ablauf; NULL = unbegrenzt).
CREATE TABLE IF NOT EXISTS zhl_cert_grant (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id      MEDIUMINT UNSIGNED NOT NULL,
  cert_type_id INT UNSIGNED NOT NULL,
  granted_at   DATETIME NOT NULL,
  expires_at   DATETIME NULL,
  granted_by   MEDIUMINT UNSIGNED NULL,
  UNIQUE KEY uq_user_type (user_id, cert_type_id),
  INDEX idx_user (user_id),
  CONSTRAINT fk_grant_type FOREIGN KEY (cert_type_id) REFERENCES zhl_cert_type (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed (Apply-Wrapper, idempotent): 5 Beispiel-Zertifikate + migrierter Schnitt-PC-Typ.
--   Videostudio→res21 · Pocket 6K→res22 · Sony ZV1→res27 · Drohne→res37 · Insta360→res12,15,51
--   Schnitt-/VR-PC→res53,54,55,56 (übernimmt bestehende F40-Grants)
-- Reverse-Migration: bestehende zhl_certificate-Grants → zhl_cert_grant; danach Projektion
-- (zhl_certificate wird aus den Grants neu aufgebaut).
