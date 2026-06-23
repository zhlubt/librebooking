-- ZHL Dashboard v2b — Bundles („Vorhaben"). Ein Bundle bündelt mehrere Geräte-Typen mit Menge
-- (US-9/US-10). Items referenzieren den Geräte-Typ über sein Label (= Wert des Custom-Attributs
-- „Geräte-Typ" aus Migration 006). Hinweis (Codex): Label ist v2b noch Freitext; eine stabile
-- Typ-ID ist die Härtung für später (Umbenennen des Typs würde Item-Zuordnung brechen).
--
-- Die Verfügbarkeit eines Bundles wird zur Laufzeit aus dem Pool je Typ berechnet (kein gespeicherter
-- Zustand). Bundle-Buchung = der Nutzer bucht die konkreten Einzelgeräte (Trichter → native Buchung);
-- atomare Multi-Ressourcen-/Sequenz-Buchung ist bewusst späterer Ausbau.

CREATE TABLE IF NOT EXISTS zhl_bundle (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150) NOT NULL,
  use_case    VARCHAR(150) NULL,                  -- „Wozu?" (z. B. „Vorlesung aufzeichnen")
  difficulty  ENUM('einfach','fortgeschritten','profi') NOT NULL DEFAULT 'einfach',
  description VARCHAR(500) NULL,
  hint        VARCHAR(500) NULL,                  -- Hinweis-Text (z. B. Folien-Tipp, Einweisung)
  active      TINYINT(1) NOT NULL DEFAULT 1,
  sort_order  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS zhl_bundle_item (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  bundle_id   INT UNSIGNED NOT NULL,
  type_label  VARCHAR(100) NOT NULL,              -- = Wert des „Geräte-Typ"-Attributs
  quantity    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  required    TINYINT(1) NOT NULL DEFAULT 1,      -- 0 = optionaler Mitbuch-Vorschlag
  note        VARCHAR(255) NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  INDEX idx_bundle (bundle_id),
  CONSTRAINT fk_bundle_item_bundle FOREIGN KEY (bundle_id) REFERENCES zhl_bundle (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
