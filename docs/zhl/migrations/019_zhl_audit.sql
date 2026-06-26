-- 019: Audit-Log (F37) — systemweites Protokoll relevanter ZHL-Aktionen.
--
-- Schreibt EINE Zeile je nennenswerter Aktion (Buchung angelegt, Übergabe-Matrix
-- gespeichert, Hauspost-Antrag, Übergabe geprüft, …). Bewusst schlank und additiv;
-- berührt keinen LibreBooking-Core. Lesen über Web/zhl-audit.php (nur Admin).
--
-- Idempotent: CREATE TABLE IF NOT EXISTS. Mehrfaches Ausführen unschädlich.

CREATE TABLE IF NOT EXISTS zhl_audit (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_utc     DATETIME       NOT NULL,
  actor_user_id   INT            NULL,
  actor_name      VARCHAR(190)   NULL,
  action          VARCHAR(64)    NOT NULL,
  entity_type     VARCHAR(48)    NULL,
  entity_id       VARCHAR(64)    NULL,
  reference_number VARCHAR(64)   NULL,
  detail          TEXT           NULL,
  ip              VARCHAR(45)    NULL,
  PRIMARY KEY (id),
  KEY idx_created (created_utc),
  KEY idx_action (action),
  KEY idx_actor (actor_user_id),
  KEY idx_ref (reference_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
