# Lokal aufsetzen (ZHL-Fork)

Wir entwickeln gegen **diesen Fork** (`zhl-main`, Basis LibreBooking 5.1.0) mit einer
**Kopie der Live-Datenbank**.

## Voraussetzungen
- PHP ≥ 8.2 (lokal 8.5 vorhanden) mit `pdo_mysql`, `mbstring`, `gd`, `curl`, `xml`, `openssl`
- Composer, Node ≥ 20 (für Frontend-Lint), Docker (für MariaDB)

## 1. Repo & Dependencies
```bash
git clone git@github.com:zhlubt/librebooking.git
cd librebooking && git checkout zhl-main
composer install
cp config/config.dist.php config/config.php   # DB-Zugang eintragen (s.u.)
```

## 2. Datenbank aus Live-Dump
```bash
# lokale MariaDB
docker run -d --name zhl-mariadb -e MARIADB_ROOT_PASSWORD=root \
  -e MARIADB_DATABASE=zhl_buchung -p 13306:3306 mariadb:11.4

# Live-Dump einspielen (read-only gezogen, liegt außerhalb des Repos)
docker exec -i zhl-mariadb mariadb -uroot -proot zhl_buchung \
  < ../zhl-buchungssystem-LIVE-COPY/zhl_live_dump.sql
```
In `config/config.php`: Host `127.0.0.1`, Port `13306`, DB `zhl_buchung`, User `root`, Pass `root`.

## 3. Starten (Produktion spiegeln: App unter /Web/)
```bash
php -S 127.0.0.1:8080          # DocumentRoot = Repo-Wurzel
# config.php: 'script.url' => 'http://127.0.0.1:8080/Web'   (MIT Pfad, wie Live)
# Aufruf:    http://127.0.0.1:8080/Web/
# tpl_c/ und uploads/ müssen beschreibbar sein
```
> Wichtig (siehe ISSUES.md #1): `script.url` muss **einen Pfad** haben (`/Web`), sonst
> baut LibreBooking nach dem Login einen kaputten Redirect `//schedule.php`. Lokal also
> immer unter `/Web/` servieren — das spiegelt die Live-Struktur (`…/Web`).

## Live-Dump neu ziehen (read-only)
Container-Shell vorhanden (`zhl-buchung@buchung.zhl-ubt.de:22`), aber **kein
`mysqldump`** im Container → Dump via PHP über die vorhandene `db.php`:
```bash
# pipe ein PHP-Dumpскript per SSH-stdin, SQL kommt lokal an (nichts wird auf Live geschrieben)
ssh -p 22 zhl-buchung@buchung.zhl-ubt.de 'php' < docs/zhl/db_dump.php > dump.sql
```
(Dump-Skript: [`docs/zhl/db_dump.php`](db_dump.php) — rein lesend, schreibt nichts auf Live.)

## Hinweis
Zugangsdaten stehen in der lokalen `.env` des Schwester-Verzeichnisses
`zhl-buchungssystem/` — **nie committen**.
