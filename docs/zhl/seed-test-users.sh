#!/usr/bin/env bash
# Legt 5 Test-User (je Rolle) in der lokalen DB-Kopie an. Passwort: zhltest123.
# Idempotent (INSERT IGNORE-artig über Email-Check). Nur für die lokale Wegwerf-DB.
#   Usage: ./docs/zhl/seed-test-users.sh   (DB-Container muss laufen)
set -euo pipefail
CONT="${ZHL_DB_CONTAINER:-zhl-mariadb}"
DB="${ZHL_DB_NAME:-zhl_buchung}"
PW="zhltest123"

q(){ docker exec "$CONT" mariadb -uroot -proot "$DB" -N -e "$1" 2>/dev/null; }

mk(){ # fname lname username email group_id(optional)
  local fn="$1" ln="$2" un="$3" em="$4" gid="${5:-}"
  if [ -n "$(q "SELECT user_id FROM users WHERE email='$em';")" ]; then echo "exists: $em"; return; fi
  local salt hash pub
  salt=$(php -r "echo substr(md5('$em'),0,8);")
  hash=$(php -r "echo sha1('$PW'.'$salt');")
  q "INSERT INTO users (fname,lname,username,email,password,salt,timezone,language,homepageid,date_created,last_modified,status_id,public_id,terms_date_accepted,allow_calendar_subscription,credit_count)
     VALUES ('$fn','$ln','$un','$em','$hash','$salt','Europe/Berlin','de_de',2,NOW(),NOW(),1,'pub_$un',NOW(),0,0);"
  [ -n "$gid" ] && q "INSERT INTO user_groups (user_id,group_id) SELECT user_id,$gid FROM users WHERE email='$em';"
  echo "created: $em (group ${gid:-none})"
}

# Gruppen: 1 Group-Admin, 2 Application-Admin, 3 Resource-Admin, 4 Schedule-Admin
mk Test Admin         zhl_admin      admin@zhl.local         2
mk Test GroupAdmin    zhl_groupadmin groupadmin@zhl.local    1
mk Test ResourceAdmin zhl_resadmin   resourceadmin@zhl.local 3
mk Test ScheduleAdmin zhl_schedadmin scheduleadmin@zhl.local 4
mk Test User          zhl_user       user@zhl.local

# Registrierung weist 'autoassign=1'-Ressourcen automatisch zu. Da wir per SQL anlegen,
# holen wir das für den regulären User nach (sonst kann er nichts buchen).
q "INSERT IGNORE INTO user_resource_permissions (user_id,resource_id,permission_id,permission_type)
   SELECT u.user_id, r.resource_id, 1, 0 FROM users u JOIN resources r ON r.autoassign=1
   WHERE u.email='user@zhl.local';"
echo "auto-assigned non-restricted resources to user@zhl.local"

# --- F40-Demo: Einweisungs-/Berechtigungspflicht (Stufe 1, nur Config) ---
# Cert-Gruppe anlegen und ihr die beschränkten Gaming-PCs (53-56, autoassign=0) freigeben.
q "INSERT INTO groups (name,isdefault) SELECT 'Eingewiesen: Gaming-PC',0
   WHERE NOT EXISTS (SELECT 1 FROM groups WHERE name='Eingewiesen: Gaming-PC');"
CERTGID=$(q "SELECT group_id FROM groups WHERE name='Eingewiesen: Gaming-PC';" | tr -d '[:space:]')
q "INSERT IGNORE INTO group_resource_permissions (group_id,resource_id,permission_type)
   SELECT $CERTGID,resource_id,0 FROM resources WHERE resource_id IN (53,54,55,56);"
# certuser: in Cert-Gruppe + autoassign-Rechte (kann offene + die Gaming-PCs buchen)
mk Test CertUser zhl_certuser certuser@zhl.local "$CERTGID"
q "INSERT IGNORE INTO user_resource_permissions (user_id,resource_id,permission_id,permission_type)
   SELECT u.user_id,r.resource_id,1,0 FROM users u JOIN resources r ON r.autoassign=1
   WHERE u.email='certuser@zhl.local';"
echo "F40-Demo: Gruppe 'Eingewiesen: Gaming-PC' (id $CERTGID) -> 53-56; certuser@zhl.local Mitglied"

# --- F40 Stufe 2: Zertifikat-Lifecycle (Permission-Plugin ZhlCertificate) ---
# Voraussetzung: docs/zhl/migrations/001_zhl_certificate.sql eingespielt.
q "INSERT IGNORE INTO zhl_certificate_required (resource_id) VALUES (53),(54),(55),(56);"
# certuser: GÜLTIGES Zertifikat (1 Jahr); certexpired: in Cert-Gruppe, aber ABGELAUFEN.
q "INSERT INTO zhl_certificate (user_id,resource_id,granted_at,expires_at)
   SELECT u.user_id,r.resource_id,NOW(),DATE_ADD(NOW(),INTERVAL 1 YEAR)
   FROM users u JOIN (SELECT 53 resource_id UNION SELECT 54 UNION SELECT 55 UNION SELECT 56) r
   WHERE u.email='certuser@zhl.local'
   ON DUPLICATE KEY UPDATE expires_at=VALUES(expires_at);"
mk Test CertExpired zhl_certexpired certexpired@zhl.local "$CERTGID"
q "INSERT IGNORE INTO user_resource_permissions (user_id,resource_id,permission_id,permission_type)
   SELECT u.user_id,r.resource_id,1,0 FROM users u JOIN resources r ON r.autoassign=1
   WHERE u.email='certexpired@zhl.local';"
q "INSERT INTO zhl_certificate (user_id,resource_id,granted_at,expires_at)
   SELECT u.user_id,r.resource_id,DATE_SUB(NOW(),INTERVAL 1 YEAR),DATE_SUB(NOW(),INTERVAL 1 DAY)
   FROM users u JOIN (SELECT 53 resource_id UNION SELECT 54 UNION SELECT 55 UNION SELECT 56) r
   WHERE u.email='certexpired@zhl.local'
   ON DUPLICATE KEY UPDATE expires_at=VALUES(expires_at);"
echo "F40 Stufe 2: certuser=gültiges Zertifikat, certexpired=abgelaufenes (in Gruppe)"

echo "--- Test-Logins (Passwort: $PW) ---"
q "SELECT u.email, COALESCE(g.name,'(regular user)') FROM users u LEFT JOIN user_groups ug ON u.user_id=ug.user_id LEFT JOIN groups g ON ug.group_id=g.group_id WHERE u.email LIKE '%@zhl.local' ORDER BY u.user_id;"
