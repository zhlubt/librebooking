-- ZHL backup zhl_bundle(+_item) vor Migration 008
DROP TABLE IF EXISTS `zhl_bundle__bak008`;
-- zhl_bundle (9 Zeilen)
INSERT INTO `zhl_bundle` (`id`,`name`,`use_case`,`difficulty`,`description`,`hint`,`active`,`sort_order`) VALUES ('1','Vlog – schnell & einfach','Schnell mit dem Handy filmen','einfach',NULL,'Halterung + Ton – sofort startklar.','1','0');
INSERT INTO `zhl_bundle` (`id`,`name`,`use_case`,`difficulty`,`description`,`hint`,`active`,`sort_order`) VALUES ('2','Vorlesung aufzeichnen (statisch)','Vortrag/Vorlesung statisch aufnehmen','einfach',NULL,'Feste Kameraposition – einfach zu bedienen.','1','1');
INSERT INTO `zhl_bundle` (`id`,`name`,`use_case`,`difficulty`,`description`,`hint`,`active`,`sort_order`) VALUES ('3','Imagefilm / bewegtes Filmen','Bewegt filmen (Imagefilm/Vlog)','fortgeschritten',NULL,'Mit Gimbal für ruhige, bewegte Aufnahmen.','1','2');
INSERT INTO `zhl_bundle` (`id`,`name`,`use_case`,`difficulty`,`description`,`hint`,`active`,`sort_order`) VALUES ('4','Profi-Film (BM Pocket 6K)','Hochwertige Filmproduktion','profi',NULL,'Mit Wechselobjektiven – Bedienung anspruchsvoll.','1','3');
INSERT INTO `zhl_bundle` (`id`,`name`,`use_case`,`difficulty`,`description`,`hint`,`active`,`sort_order`) VALUES ('5','VR-Lehrveranstaltung','VR mit der Gruppe','einfach',NULL,'Anzahl der Brillen nach Teilnehmerzahl (im Assistenten wählbar).','1','4');
INSERT INTO `zhl_bundle` (`id`,`name`,`use_case`,`difficulty`,`description`,`hint`,`active`,`sort_order`) VALUES ('6','Podcast aufnehmen','Podcast aufnehmen','einfach',NULL,'Wo aufnehmen? Seminarraum oder Studio. Einweisung empfehlenswert.','1','5');
INSERT INTO `zhl_bundle` (`id`,`name`,`use_case`,`difficulty`,`description`,`hint`,`active`,`sort_order`) VALUES ('7','Videostudio-Aufzeichnung','Im Videostudio aufnehmen','fortgeschritten',NULL,'Folien so bauen, dass rechts unten Platz zum Stehen bleibt. Einweisung zwingend.','1','6');
INSERT INTO `zhl_bundle` (`id`,`name`,`use_case`,`difficulty`,`description`,`hint`,`active`,`sort_order`) VALUES ('8','Drohnenaufnahme','Luftaufnahmen','profi',NULL,'Einweisung zwingend vor Ausgabe.','1','7');
INSERT INTO `zhl_bundle` (`id`,`name`,`use_case`,`difficulty`,`description`,`hint`,`active`,`sort_order`) VALUES ('9','360-Grad-Aufnahme (Insta360)','360-Grad-/VR-Content','fortgeschritten',NULL,'Vorab Gespräch mit unserem Experten zur Weiterverarbeitung.','1','8');
DROP TABLE IF EXISTS `zhl_bundle_item__bak008`;
-- zhl_bundle_item (23 Zeilen)
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('1','1','Smartphone-Video-Kit','1','1','','0');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('2','1','Funkmikrofon','1','1','','1');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('3','2','Kamera','1','1','','0');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('4','2','Stativ','1','1','','1');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('5','2','Funkmikrofon','1','1','','2');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('6','3','Kamera','1','1','','0');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('7','3','Funkmikrofon','3','1','','1');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('8','3','Stativ','1','1','','2');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('9','3','Gimbal','1','1','','3');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('10','3','Schnitt-/VR-PC','1','0','für den Schnitt im Anschluss','4');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('11','4','Kamera','1','1','Blackmagic Pocket 6K','0');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('12','4','Objektiv','3','1','','1');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('13','4','Stativ','1','1','','2');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('14','4','Gimbal','1','1','','3');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('15','4','Schnitt-/VR-PC','1','0','für den Schnitt','4');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('16','5','VR-Brille','5','1','Menge nach Teilnehmerzahl','0');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('17','6','Podcast-Mikrofon','2','1','','0');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('18','6','Schnitt-/VR-PC','1','0','Schnittlaptop, optional','1');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('19','7','Videostudio','1','1','','0');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('20','7','Funkmikrofon','1','1','pro Person, bis 4','1');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('21','7','Schnitt-/VR-PC','1','0','Laptop mit USB-C für Folien','2');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('22','8','Drohne','1','1','','0');
INSERT INTO `zhl_bundle_item` (`id`,`bundle_id`,`type_label`,`quantity`,`required`,`note`,`sort_order`) VALUES ('23','9','360-Grad-Kamera','1','1','','0');
