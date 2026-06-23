-- ZHL Dashboard v2 — „Geräte-Typ" als Laien-Tag (US-6/US-7) + Pool-Schlüssel (US-12/US-14).
-- Ein Ressourcen-Custom-Attribut (Kategorie RESOURCE=4, Typ SINGLE_LINE=1). Es dient gleichzeitig
-- als verständlicher Begriff fürs Dashboard („Funkmikrofon" statt „DJI mic") UND als Schlüssel, um
-- gleiche Einzelgeräte zu zählen (Pool). Pflege später über die native Resource-Admin-UI.
-- Beides idempotent (re-runnable). Seed = Heuristik aus den Gerätenamen; vom ZHL-Team verfeinerbar.

-- 1) Attribut-Definition (nur anlegen, wenn nicht vorhanden).
INSERT INTO custom_attributes (display_label, display_type, attribute_category, is_required, sort_order)
SELECT 'Geräte-Typ', 1, 4, 0, 1
WHERE NOT EXISTS (
  SELECT 1 FROM custom_attributes WHERE display_label = 'Geräte-Typ' AND attribute_category = 4
);

-- 2) Seed der Werte (idempotent: erst die Werte dieses Attributs leeren, dann neu setzen).
SET @aid = (SELECT custom_attribute_id FROM custom_attributes WHERE display_label = 'Geräte-Typ' AND attribute_category = 4 LIMIT 1);

DELETE FROM custom_attribute_values WHERE custom_attribute_id = @aid AND attribute_category = 4;

INSERT INTO custom_attribute_values (custom_attribute_id, attribute_value, entity_id, attribute_category)
SELECT @aid, typ, resource_id, 4 FROM (
  SELECT resource_id,
    CASE
      WHEN name LIKE 'DJI mic%' OR name LIKE '%Wireless GO%'                         THEN 'Funkmikrofon'
      WHEN name LIKE '%Videomic%'                                                     THEN 'Richtmikrofon'
      WHEN name LIKE '%Yeti%' OR name LIKE 'Podcast Bundle%' OR name LIKE '%SM7B%'    THEN 'Podcast-Mikrofon'
      WHEN name LIKE 'Meta Quest%' OR name LIKE 'Pico 4%'                             THEN 'VR-Brille'
      WHEN name LIKE 'Hololens%'                                                      THEN 'AR-Brille'
      WHEN name LIKE 'Insta360%'                                                      THEN '360-Grad-Kamera'
      WHEN name LIKE 'Blackmagic%' OR name LIKE 'Sony ZV1%'                           THEN 'Kamera'
      WHEN name LIKE '%Gimbal%'                                                       THEN 'Gimbal'
      WHEN name LIKE 'DJI mini%'                                                      THEN 'Drohne'
      WHEN name LIKE 'Sigma %' OR name LIKE 'Tokina %'                                THEN 'Objektiv'
      WHEN name LIKE 'Stativ%'                                                        THEN 'Stativ'
      WHEN name LIKE '%Gaming-PC%' OR name LIKE '%Videoschnitt%'                      THEN 'Schnitt-/VR-PC'
      WHEN name LIKE 'All-in-One Video Kit%'                                          THEN 'Smartphone-Video-Kit'
      WHEN name LIKE 'Flipchart%' OR name LIKE 'Metaplanwand%' OR name LIKE 'Moderation%' THEN 'Moderationsmaterial'
      WHEN name LIKE '%Videostudio%'                                                  THEN 'Videostudio'
      ELSE NULL
    END AS typ
  FROM resources
) x
WHERE typ IS NOT NULL;
