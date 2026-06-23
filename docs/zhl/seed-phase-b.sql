-- Phase-B-Test-Fixtures (Übergabe-Checkliste) für tests-e2e/handover-check.spec.js.
-- Idempotent. Einspielen lokal:
--   docker exec -i zhl-mariadb mariadb -uroot -proot zhl_buchung < docs/zhl/seed-phase-b.sql
--
-- Legt zwei OPTIONALE Test-Accessories auf Ressource 5 (min/max=0 → blockt die
-- normalen Buchungstests NICHT) sowie eine bestätigte Übergabe an.

DELETE FROM zhl_handover_check WHERE handover_token = 'phaseBtest1234';
DELETE FROM zhl_booking_handover WHERE handover_token = 'phaseBtest1234';
DELETE FROM resource_accessories WHERE resource_id = 5
  AND accessory_id IN (SELECT accessory_id FROM accessories WHERE accessory_name IN ('TEST Ladekabel','TEST Akku'));
DELETE FROM accessories WHERE accessory_name IN ('TEST Ladekabel','TEST Akku');

INSERT INTO accessories (accessory_name, accessory_quantity) VALUES ('TEST Ladekabel', 5), ('TEST Akku', 3);
INSERT INTO resource_accessories (resource_id, accessory_id, minimum_quantity, maximum_quantity)
  SELECT 5, accessory_id, 0, 0 FROM accessories WHERE accessory_name IN ('TEST Ladekabel','TEST Akku');

INSERT INTO zhl_booking_handover
  (handover_token, type, reference_number, resource_id, staff_role, status, created_at, updated_at)
  VALUES ('phaseBtest1234', 'pickup', 'PHASEB-REF', 5, 'primary', 'confirmed', NOW(), NOW());
