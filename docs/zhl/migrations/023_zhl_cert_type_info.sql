-- 023: Vertrauliche Zusatz-Infos je Zertifikats-Typ (D3, 2026-06-27).
--
-- Wer ein Zertifikat (zhl_cert_grant, nicht abgelaufen) besitzt, sieht NUR EINGELOGGT in „Mein Konto"
-- vertrauliche Zusatzinfos zu genau diesem Zertifikat: Transponder-Tresor-Code, wichtige News, optional
-- ein Dokument-Link. NIE in E-Mails/Logs. Pflege im Zertifikats-Admin (zhl-certificates-admin.php).
--
-- Eine Zeile je Zertifikats-Typ (PK = cert_type_id, FK → zhl_cert_type, CASCADE räumt beim Löschen auf).
-- Idempotent: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS zhl_cert_type_info (
  cert_type_id     INT UNSIGNED NOT NULL,
  transponder_code VARCHAR(190) NOT NULL DEFAULT '',
  news_text        TEXT NULL,
  doc_url          VARCHAR(500) NOT NULL DEFAULT '',
  active           TINYINT(1) NOT NULL DEFAULT 1,
  updated_at       DATETIME NULL,
  PRIMARY KEY (cert_type_id),
  CONSTRAINT fk_cert_type_info_type FOREIGN KEY (cert_type_id)
    REFERENCES zhl_cert_type (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
