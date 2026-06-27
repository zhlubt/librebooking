-- ZHL Settings — generischer Key/Value-Konfigspeicher (admin-pflegbar, additiv).
-- Erste Nutzung: Schwellen + Empfänger für den wöchentlichen Einführungs-/Übergabetermine-Report
-- (Jobs/zhl_einfuehrung_report.php, Admin: Web/zhl-einfuehrung-report-admin.php).
-- Idempotent: CREATE IF NOT EXISTS + INSERT IGNORE.

CREATE TABLE IF NOT EXISTS zhl_settings (
  k          VARCHAR(64) NOT NULL,
  v          TEXT NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO zhl_settings (k, v, updated_at) VALUES
  ('einf_report_min_days',  '1',                          NOW()),
  ('einf_report_min_appts', '1',                          NOW()),
  ('einf_report_recipient', 'zhlmedien@uni-bayreuth.de',  NOW());
