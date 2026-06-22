# Upgrade-Runbook: LibreBooking 4.0.0 → 5.1.0

> Erst **lokal an der Live-Kopie proben**, dann produktiv im Wartungsfenster.
> Zwischen 4.0 und 5.1 gibt es **keine Breaking-DB-Changes** (Schema-Upgrades reichen
> nur bis „4.0"), daher primär Code-Austausch + ggf. einmaliger DB-Upgrade-Lauf.

## Vorbereitung
- [ ] Vollständiges **DB-Backup** der Live-MariaDB (`zhl_buchung`) (siehe LOCAL-DEV §DB).
- [ ] Backup von `config.php`, `private/config/`, `public/uploads/`, `private/uploads/`.
- [ ] LibreBooking **5.1.0**-Quellen bereit (`/tmp/lb-5.1.0/`, Tag `v5.1.0`).
- [ ] `config.php` gegen `config.dist.php` der 5.1.0 diffen → neue/umbenannte Keys notieren.

## Bekannte Config-Änderungen 4.0 → 5.1
- **LDAP** host/port → URI-Format (ab 5.0.0). *Irrelevant*, da Self-Registration aktiv.
- Restliche Releases: Bugfixes, dt. Lokalisierung (5.0.3), PHP-8.5-Kompat (5.0.3),
  Security-Hardening/Rich-Text-Sanitizing (5.1.0). Keine Config-Pflichtmigration.

## Rehearsal (lokal, an der Kopie)
1. Live-Code-Kopie + DB-Kopie lokal lauffähig (LOCAL-DEV.md).
2. Code durch 5.1.0 ersetzen, **`config.php`/`private/config/` und `uploads/` behalten**.
3. `composer install` (vendor wurde im Mirror ausgelassen).
4. Web-Installer/DB-Upgrade von LibreBooking aufrufen → wendet ausstehende
   `database_schema/upgrades/*` idempotent an.
5. Smoke-Test: Login, Reservierung anlegen/ändern/stornieren, Admin-Reports, E-Mail,
   deutsche Sprache, Kalenderansicht (FullCalendar 6.1).

## Produktiv-Deploy (SFTP, Port 2222)
> Kein Shell-Zugriff → reiner Datei-Upload. DB-Upgrade läuft über die Web-Oberfläche.
1. Wartungshinweis aktivieren (Announcement / `.htaccess`-Sperre).
2. **Frisches Backup** (Code + DB) unmittelbar vor dem Upgrade.
3. 5.1.0-Code per SFTP hochladen (Overlay-Dateien zuletzt; `config.php`,
   `private/`, `uploads/`, `tpl_c/` nicht überschreiben).
4. DB-Upgrade über LibreBooking-Adminseite ausführen.
5. Smoke-Test wie oben. Wartungshinweis entfernen.

## Rollback
- Code: Backup-Stand zurück-uploaden.
- DB: Backup-Dump einspielen (nur nötig, falls DB-Upgrade lief).
