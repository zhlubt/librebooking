-- 030: Verleih-Layouts auf STÜNDLICHES Raster umstellen (SPEC-LOAN-RASTER)
--
-- Befund Live-Dump 2026-06-22: layout 10/11 = 2h, 17 = 3h, 13 = 24h — KEINES hat 09:00/15:00 als
-- Periodengrenze. Für 9/15-Übergabe-Anker UND 60-Min-Einführungen brauchen diese Layouts stündliche,
-- voll reservierbare Blöcke (00:00–24:00). Die 9/15-Beschränkung macht die App (booking_mode='anker'),
-- das Schedule bleibt permissiv → bestehende stundengenaue Reservierungen (inkl. 24h „Analoge
-- Moderation") bleiben gültig. Studio-Layout 20 (07–21 stündlich) bleibt unberührt.
--
-- !!! CORE-TABELLE time_blocks, betrifft ALLE Geräte dieser Schedules (5,2,6,7).
-- !!! ZUERST auf Staging (media.zhl-ubt.de) testen, dann mit Prod-DB-Backup ausführen.
-- !!! Alternative (sicherer): Umstellung über die LibreBooking-Admin-UI (Manage Schedules → Layout),
-- !!!   die korrekte time_blocks schreibt. Diese SQL ist die explizite, geprüfte Variante.
-- !!! Vor dem Lauf den Live-Stand der betroffenen Layouts gegenprüfen (Dump ist nicht taggleich).
--
-- Idempotent: pro Layout DELETE + INSERT der 24 Stundenblöcke (Re-Run erzeugt denselben Satz).
-- block_id wird nirgends per FK referenziert (Reservierungen speichern Zeiten, keine block_ids).

DELETE FROM time_blocks WHERE layout_id = 10;
INSERT INTO time_blocks (label,end_label,availability_code,layout_id,start_time,end_time,day_of_week) VALUES
  (NULL,NULL,1,10,'00:00:00','01:00:00',NULL),
  (NULL,NULL,1,10,'01:00:00','02:00:00',NULL),
  (NULL,NULL,1,10,'02:00:00','03:00:00',NULL),
  (NULL,NULL,1,10,'03:00:00','04:00:00',NULL),
  (NULL,NULL,1,10,'04:00:00','05:00:00',NULL),
  (NULL,NULL,1,10,'05:00:00','06:00:00',NULL),
  (NULL,NULL,1,10,'06:00:00','07:00:00',NULL),
  (NULL,NULL,1,10,'07:00:00','08:00:00',NULL),
  (NULL,NULL,1,10,'08:00:00','09:00:00',NULL),
  (NULL,NULL,1,10,'09:00:00','10:00:00',NULL),
  (NULL,NULL,1,10,'10:00:00','11:00:00',NULL),
  (NULL,NULL,1,10,'11:00:00','12:00:00',NULL),
  (NULL,NULL,1,10,'12:00:00','13:00:00',NULL),
  (NULL,NULL,1,10,'13:00:00','14:00:00',NULL),
  (NULL,NULL,1,10,'14:00:00','15:00:00',NULL),
  (NULL,NULL,1,10,'15:00:00','16:00:00',NULL),
  (NULL,NULL,1,10,'16:00:00','17:00:00',NULL),
  (NULL,NULL,1,10,'17:00:00','18:00:00',NULL),
  (NULL,NULL,1,10,'18:00:00','19:00:00',NULL),
  (NULL,NULL,1,10,'19:00:00','20:00:00',NULL),
  (NULL,NULL,1,10,'20:00:00','21:00:00',NULL),
  (NULL,NULL,1,10,'21:00:00','22:00:00',NULL),
  (NULL,NULL,1,10,'22:00:00','23:00:00',NULL),
  (NULL,NULL,1,10,'23:00:00','00:00:00',NULL);

DELETE FROM time_blocks WHERE layout_id = 11;
INSERT INTO time_blocks (label,end_label,availability_code,layout_id,start_time,end_time,day_of_week) VALUES
  (NULL,NULL,1,11,'00:00:00','01:00:00',NULL),
  (NULL,NULL,1,11,'01:00:00','02:00:00',NULL),
  (NULL,NULL,1,11,'02:00:00','03:00:00',NULL),
  (NULL,NULL,1,11,'03:00:00','04:00:00',NULL),
  (NULL,NULL,1,11,'04:00:00','05:00:00',NULL),
  (NULL,NULL,1,11,'05:00:00','06:00:00',NULL),
  (NULL,NULL,1,11,'06:00:00','07:00:00',NULL),
  (NULL,NULL,1,11,'07:00:00','08:00:00',NULL),
  (NULL,NULL,1,11,'08:00:00','09:00:00',NULL),
  (NULL,NULL,1,11,'09:00:00','10:00:00',NULL),
  (NULL,NULL,1,11,'10:00:00','11:00:00',NULL),
  (NULL,NULL,1,11,'11:00:00','12:00:00',NULL),
  (NULL,NULL,1,11,'12:00:00','13:00:00',NULL),
  (NULL,NULL,1,11,'13:00:00','14:00:00',NULL),
  (NULL,NULL,1,11,'14:00:00','15:00:00',NULL),
  (NULL,NULL,1,11,'15:00:00','16:00:00',NULL),
  (NULL,NULL,1,11,'16:00:00','17:00:00',NULL),
  (NULL,NULL,1,11,'17:00:00','18:00:00',NULL),
  (NULL,NULL,1,11,'18:00:00','19:00:00',NULL),
  (NULL,NULL,1,11,'19:00:00','20:00:00',NULL),
  (NULL,NULL,1,11,'20:00:00','21:00:00',NULL),
  (NULL,NULL,1,11,'21:00:00','22:00:00',NULL),
  (NULL,NULL,1,11,'22:00:00','23:00:00',NULL),
  (NULL,NULL,1,11,'23:00:00','00:00:00',NULL);

DELETE FROM time_blocks WHERE layout_id = 13;
INSERT INTO time_blocks (label,end_label,availability_code,layout_id,start_time,end_time,day_of_week) VALUES
  (NULL,NULL,1,13,'00:00:00','01:00:00',NULL),
  (NULL,NULL,1,13,'01:00:00','02:00:00',NULL),
  (NULL,NULL,1,13,'02:00:00','03:00:00',NULL),
  (NULL,NULL,1,13,'03:00:00','04:00:00',NULL),
  (NULL,NULL,1,13,'04:00:00','05:00:00',NULL),
  (NULL,NULL,1,13,'05:00:00','06:00:00',NULL),
  (NULL,NULL,1,13,'06:00:00','07:00:00',NULL),
  (NULL,NULL,1,13,'07:00:00','08:00:00',NULL),
  (NULL,NULL,1,13,'08:00:00','09:00:00',NULL),
  (NULL,NULL,1,13,'09:00:00','10:00:00',NULL),
  (NULL,NULL,1,13,'10:00:00','11:00:00',NULL),
  (NULL,NULL,1,13,'11:00:00','12:00:00',NULL),
  (NULL,NULL,1,13,'12:00:00','13:00:00',NULL),
  (NULL,NULL,1,13,'13:00:00','14:00:00',NULL),
  (NULL,NULL,1,13,'14:00:00','15:00:00',NULL),
  (NULL,NULL,1,13,'15:00:00','16:00:00',NULL),
  (NULL,NULL,1,13,'16:00:00','17:00:00',NULL),
  (NULL,NULL,1,13,'17:00:00','18:00:00',NULL),
  (NULL,NULL,1,13,'18:00:00','19:00:00',NULL),
  (NULL,NULL,1,13,'19:00:00','20:00:00',NULL),
  (NULL,NULL,1,13,'20:00:00','21:00:00',NULL),
  (NULL,NULL,1,13,'21:00:00','22:00:00',NULL),
  (NULL,NULL,1,13,'22:00:00','23:00:00',NULL),
  (NULL,NULL,1,13,'23:00:00','00:00:00',NULL);

DELETE FROM time_blocks WHERE layout_id = 17;
INSERT INTO time_blocks (label,end_label,availability_code,layout_id,start_time,end_time,day_of_week) VALUES
  (NULL,NULL,1,17,'00:00:00','01:00:00',NULL),
  (NULL,NULL,1,17,'01:00:00','02:00:00',NULL),
  (NULL,NULL,1,17,'02:00:00','03:00:00',NULL),
  (NULL,NULL,1,17,'03:00:00','04:00:00',NULL),
  (NULL,NULL,1,17,'04:00:00','05:00:00',NULL),
  (NULL,NULL,1,17,'05:00:00','06:00:00',NULL),
  (NULL,NULL,1,17,'06:00:00','07:00:00',NULL),
  (NULL,NULL,1,17,'07:00:00','08:00:00',NULL),
  (NULL,NULL,1,17,'08:00:00','09:00:00',NULL),
  (NULL,NULL,1,17,'09:00:00','10:00:00',NULL),
  (NULL,NULL,1,17,'10:00:00','11:00:00',NULL),
  (NULL,NULL,1,17,'11:00:00','12:00:00',NULL),
  (NULL,NULL,1,17,'12:00:00','13:00:00',NULL),
  (NULL,NULL,1,17,'13:00:00','14:00:00',NULL),
  (NULL,NULL,1,17,'14:00:00','15:00:00',NULL),
  (NULL,NULL,1,17,'15:00:00','16:00:00',NULL),
  (NULL,NULL,1,17,'16:00:00','17:00:00',NULL),
  (NULL,NULL,1,17,'17:00:00','18:00:00',NULL),
  (NULL,NULL,1,17,'18:00:00','19:00:00',NULL),
  (NULL,NULL,1,17,'19:00:00','20:00:00',NULL),
  (NULL,NULL,1,17,'20:00:00','21:00:00',NULL),
  (NULL,NULL,1,17,'21:00:00','22:00:00',NULL),
  (NULL,NULL,1,17,'22:00:00','23:00:00',NULL),
  (NULL,NULL,1,17,'23:00:00','00:00:00',NULL);
