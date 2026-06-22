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

echo "--- Test-Logins (Passwort: $PW) ---"
q "SELECT u.email, COALESCE(g.name,'(regular user)') FROM users u LEFT JOIN user_groups ug ON u.user_id=ug.user_id LEFT JOIN groups g ON ug.group_id=g.group_id WHERE u.email LIKE '%@zhl.local' ORDER BY u.user_id;"
