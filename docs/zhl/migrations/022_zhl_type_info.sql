-- 022: Info-Material je Geräte-Typ (D2, 2026-06-26).
--
-- Pro Geräte-Typ (Attr „Geräte-Typ", Label = type_label) lässt sich ein Info-Link
-- (Webseite / PDF / Video) und ein kurzer Info-Text hinterlegen. Wird nach der Buchung
-- auf der Erfolgsseite + per E-Mail angezeigt („Geräte-Name → Info-Material") und als
-- kleines (i) am Bundle. Pflegbar über zhl-typeinfo-admin.php.
--
-- Idempotent: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS zhl_type_info (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type_label VARCHAR(190) NOT NULL,
  info_url   VARCHAR(500) NOT NULL DEFAULT '',
  info_text  TEXT NULL,
  active     TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_type_info_label (type_label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
