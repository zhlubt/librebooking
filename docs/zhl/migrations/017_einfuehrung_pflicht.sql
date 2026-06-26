-- 017: Einführungspflicht je Gerät (Nutzer-Vorgabe 2026-06-25)
--
-- einfuehrung = 'notwendig' → zwingende vorherige Einweisung (blockiert Buchung bis Slot gebucht)
-- einfuehrung = 'moeglich'  → "kurze Besprechung vorschlagen" (Picker sichtbar, NICHT blockierend)
--
-- Typ "Einführung in Medien" (member 2) liefert live 12 Slots → Gate blockiert nicht.
-- Neue Zeilen erben die Tabellen-Defaults für abholung('abholen')/booking_mode('day')/
-- vorlauf_toleranz_h(0)/hauspost_allowed(0) → keine Verhaltensänderung außer der Einführung.
-- ON DUPLICATE KEY UPDATE rührt NUR die Einführungs-Spalten an (abholung etc. bestehender Zeilen bleibt).

-- (A) ZWINGENDE Einweisung:
--  22 Pocket 6K · 28 Podcast-Bundle 2x Shure · 68 DJI RS3 Pro Gimbal ·
--  53/54/55/56 Schnitt-/VR-PC · 15 Insta360 X3 · 51 Insta360 X4
--  (21 ZHL Videostudio ist bereits 'notwendig')
INSERT INTO zhl_uebergabe (resource_id, einfuehrung, einfuehrung_typ, tp_member_id, updated_at) VALUES
 (22,'notwendig','Einführung in Medien',2,NOW()),
 (28,'notwendig','Einführung in Medien',2,NOW()),
 (68,'notwendig','Einführung in Medien',2,NOW()),
 (53,'notwendig','Einführung in Medien',2,NOW()),
 (54,'notwendig','Einführung in Medien',2,NOW()),
 (55,'notwendig','Einführung in Medien',2,NOW()),
 (56,'notwendig','Einführung in Medien',2,NOW()),
 (15,'notwendig','Einführung in Medien',2,NOW()),
 (51,'notwendig','Einführung in Medien',2,NOW())
ON DUPLICATE KEY UPDATE einfuehrung=VALUES(einfuehrung), einfuehrung_typ=VALUES(einfuehrung_typ), tp_member_id=VALUES(tp_member_id), updated_at=NOW();

-- (B) "kurze Besprechung vorschlagen" (moeglich, nicht blockierend):
--  alle VR/AR-Brillen (5-10,16-20,38-43) · weitere Mikrofone (29 Blue Yeti, 65/66 Rode Videomic)
--  Randfälle (nicht explizit genannt, daher nur Vorschlag): 12 Insta360 Pro 2, 69 DJI Ronin SC
--  (Funkmikrofone 45-47,52,57-61 sind bereits 'moeglich')
INSERT INTO zhl_uebergabe (resource_id, einfuehrung, einfuehrung_typ, tp_member_id, updated_at) VALUES
 (5,'moeglich','Einführung in Medien',2,NOW()),
 (6,'moeglich','Einführung in Medien',2,NOW()),
 (7,'moeglich','Einführung in Medien',2,NOW()),
 (8,'moeglich','Einführung in Medien',2,NOW()),
 (9,'moeglich','Einführung in Medien',2,NOW()),
 (10,'moeglich','Einführung in Medien',2,NOW()),
 (16,'moeglich','Einführung in Medien',2,NOW()),
 (17,'moeglich','Einführung in Medien',2,NOW()),
 (18,'moeglich','Einführung in Medien',2,NOW()),
 (19,'moeglich','Einführung in Medien',2,NOW()),
 (20,'moeglich','Einführung in Medien',2,NOW()),
 (38,'moeglich','Einführung in Medien',2,NOW()),
 (39,'moeglich','Einführung in Medien',2,NOW()),
 (40,'moeglich','Einführung in Medien',2,NOW()),
 (41,'moeglich','Einführung in Medien',2,NOW()),
 (42,'moeglich','Einführung in Medien',2,NOW()),
 (43,'moeglich','Einführung in Medien',2,NOW()),
 (29,'moeglich','Einführung in Medien',2,NOW()),
 (65,'moeglich','Einführung in Medien',2,NOW()),
 (66,'moeglich','Einführung in Medien',2,NOW()),
 (12,'moeglich','Einführung in Medien',2,NOW()),
 (69,'moeglich','Einführung in Medien',2,NOW())
ON DUPLICATE KEY UPDATE einfuehrung=VALUES(einfuehrung), einfuehrung_typ=VALUES(einfuehrung_typ), tp_member_id=VALUES(tp_member_id), updated_at=NOW();
