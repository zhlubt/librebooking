# Cron-Runner einrichten (SFTP-only Hosting, kein Container-Cron)

> Problem (verifiziert): Der Live-Container hat **keinen Cron** → LibreBooking-Jobs
> (Reminder/Waitlist/Missed-Checkin/…) laufen nicht. Lösung: token-geschützter
> **Web-Cron-Endpunkt** + **externer Scheduler**.

## Teil 1 — Endpunkt (gebaut, deploybar)
`Web/zhl-cron.php` führt die Jobs als CLI-Prozesse aus (die Jobs verlangen via JobCop CLI).
Token aus `config/zhl-cron.php` (`$zhlCronToken`, **nicht im Repo**) oder Env `ZHL_CRON_TOKEN`.
Lokal verifiziert: falscher Token → 403, richtiger → alle Jobs `exit 0`.

**Deploy auf Live (eigener Schritt, mit Backup!):**
1. `Web/zhl-cron.php` per SFTP hochladen.
2. `config/zhl-cron.php` mit einem **starken** Token anlegen (nur auf dem Server):
   ```php
   <?php $zhlCronToken = '<langer-zufallstoken>';
   ```
3. Test: `curl -fsS "https://buchung.zhl-ubt.de/Web/zhl-cron.php?token=<TOKEN>"`.

## Teil 2 — Scheduler (extern, ruft die URL alle 5 Min)
Da das Hosting keinen Cron erlaubt, muss der Scheduler **außerhalb** laufen. Optionen:

| Option | Eignung | Hinweis |
|---|---|---|
| **Gaming-PC** (immer an, schon für Deploys genutzt) | ✅ empfohlen | cron/Task hält die URL warm |
| **Mac launchd** (`de.zhl.buchung-cron.plist`) | ⚠️ nur wenn Mac läuft | Template dabei |
| **Externer Dienst** (cron-job.org) | ✅ zuverlässig | Token/URL liegt bei Drittanbieter |

### launchd (Mac) — Template
`docs/zhl/de.zhl.buchung-cron.plist` (URL/Token anpassen) nach
`~/Library/LaunchAgents/` kopieren, dann:
```bash
launchctl load ~/Library/LaunchAgents/de.zhl.buchung-cron.plist
launchctl start de.zhl.buchung-cron
```
Ruft alle 300 s `zhl-cron.php` auf, Log nach `/tmp/zhl-cron.log`.

### generisch (Gaming-PC / Linux cron)
```cron
*/5 * * * * curl -fsS "https://buchung.zhl-ubt.de/Web/zhl-cron.php?token=<TOKEN>" >/dev/null
```

## Offen / Entscheidung
- **Wo** soll der Scheduler laufen (Gaming-PC / Mac / externer Dienst)?
- **Deploy-Zeitpunkt:** `zhl-cron.php` jetzt auf Live-4.0.0 oder erst mit dem 5.1.0-Upgrade?
  (Vorher Backup — CLAUDE.md-Regel.)
