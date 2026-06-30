# SPEC-SELBSTRUECKGABE — Selbst-Rückgabe kleiner Medien (QR + Foto + Mail-Nachweis)

Status: in Arbeit (2026-06-29). Vom Nutzer beauftragt: kleine Medien sollen nicht nur zu
Übergabezeiten, sondern **jederzeit an einem vorbereiteten Ort** (Cateringwagen / Videostudio)
zurückgegeben werden können — mit QR-Code vor Ort, Foto-Nachweis und Mail an alle Beteiligten,
„damit wir exakt wissen, wann was wie zurückgegeben wurde".

Abgrenzung: **Pflicht-Rückgabe** (`zhl_uebergabe.rueckgabe='abgeben_persoenlich'`, SPEC-RUECKGABE,
live) ist bereits geklärt und bleibt **unangetastet**. Diese Spec betrifft nur kleine Medien mit
`rueckgabe IN ('abgeben','nicht_noetig')`.

## Entscheidungen (Nutzer 2026-06-29)
1. **Status nach Ablage = „Erst Team bestätigt".** Selbst-Ablage erzeugt eine **gemeldete** Rückgabe
   (`zhl_self_return.status='reported'`): Zeitpunkt, Ort, Foto + Mail an alle. Das Gerät bleibt
   gesperrt, bis der Medienmanager es physisch holt und über die **bestehende** Checkliste
   (`zhl-handover-check.php`, `type=return`) prüft → erst dann `zhl_booking_handover.status='done'`
   + wieder ausleihbar. Das Foto ist **Ablagebeleg**, keine Zustandsfreigabe.
2. **Foto = Pflicht.** Kamera direkt in der App (`<input type=file accept=image/* capture=environment>`).
   Ohne Foto kein Absenden. **Ein** Foto je Ablage-Vorgang (zeigt die abgelegten Medien zusammen).
3. **Standorte jetzt:** Cateringwagen **und** Videostudio, je eigener QR; weitere per Admin erweiterbar.
4. **Geltungsbereich:** automatisch alle Geräte mit `rueckgabe IN ('abgeben','nicht_noetig')`.

## Identifikation des Rückgebenden
- **Eingeloggt:** bestehende LB-Session wird ohne Zwangs-Redirect erkannt
  (`ServiceLocator::GetServer()->GetUserSession()->IsLoggedIn()`).
- **Login-frei per Magic-Link:** Nutzer gibt nur seine E-Mail ein → erhält einen Link
  (`?loc=…&t=<token>`), der ihn (für 2 h, einmalig) identifiziert. **Immer** neutrale Antwort
  („Falls ein Konto mit dieser Adresse existiert …") gegen Account-Enumeration.
- In beiden Fällen sieht der Nutzer **seine** aktuell ausgeliehenen, selbst-rückgabefähigen Medien
  und wählt, welche er gerade ablegt (Default: alle).

## Datenmodell — `migrations/030_zhl_self_return.sql`
- **`zhl_return_location`**: `id, slug(UNIQUE), label, qr_token(UNIQUE), active, note, created_at,
  updated_at`. Seed Cateringwagen + Videostudio; `qr_token` beim Erst-Insert via `RANDOM_BYTES`,
  stabil über Re-Runs (`ON DUPLICATE KEY UPDATE slug=slug`).
- **`zhl_self_return`**: `id, location_id, user_id?, email?, handover_id(→zhl_booking_handover.id),
  reference_number, resource_id, photo_path, note, reported_at(UTC), status(reported|confirmed|
  cancelled), confirmed_handover_check_id?, created_at`. Eine Zeile je abgelegtem Gerät; ein Batch
  teilt sich `photo_path` + `reported_at`.
- **`zhl_return_access`**: `token(CHAR40,PK), user_id, location_id?, created_at, expires_at, used_at`.

Rein additiv — keine bestehende Tabelle wird geändert.

## Komponenten
- `Web/zhl-return-lib.php` — Helfer (raw PDO via `zhl_handover_db()` aus `zhl-handover-lib.php`).
- `Web/zhl-return-drop.php` — öffentliche QR-Landeseite (Standalone, Vorbild `zhl-termin-auswahl.php`).
- `Web/zhl-return-qr.php` — QR-PNG je Standort (BaconQrCode, Admin), kodiert die absolute Drop-URL.
- `Web/zhl-return-photo.php` — geschützter Foto-Stream (`?id=`, Admin **oder** Eigentümer).
- `Web/zhl-return-locations-admin.php` — Standorte verwalten + QR drucken (Admin).
- Foto-Ablage: **privat** unter `<root>/uploads/zhl-return/<Jahr>/<random>.<ext>` (root-`uploads/`
  trägt `deny from all`). Validierung: finfo-MIME ∈ {jpeg,png,webp,heic/heif}, ≤ 12 MB.

## Staff-Integration (additiv, leicht)
- `zhl-resource-return.php`: Badge „Vom Nutzer am <Ort> abgelegt am <Zeit>" + Foto-Link, falls gemeldet.
- `zhl-handover-check.php`: beim Abschluss best-effort
  `UPDATE zhl_self_return SET status='confirmed', confirmed_handover_check_id=? WHERE handover_id=? AND status='reported'`.
- `zhl-medienmanager.php`: fällige Rückgaben um „bereits abgelegt"-Hinweis ergänzen.

## Akzeptanzkriterien
- **AK-1** QR-Scan eines aktiven Standorts öffnet die Drop-Seite; inaktiver/unbekannter Token →
  freundliche Ablehnung, kein Datenleck.
- **AK-2** Eingeloggter Nutzer sieht ausschließlich **seine** offenen, selbst-rückgabefähigen Medien.
- **AK-3** E-Mail-Pfad liefert (nur bei existierendem Konto) einen Magic-Link; Antwort ist immer neutral.
- **AK-4** Absenden ohne Foto oder ohne ausgewähltes Medium wird blockiert.
- **AK-5** Erfolgreiches Absenden erzeugt je Medium eine `zhl_self_return`-Zeile (`reported`), legt das
  Foto privat ab und schickt eine Mail **mit Foto-Anhang** an Nutzer **und** Team.
- **AK-6** Gerät bleibt nach Meldung gesperrt; erst der Checklisten-Abschluss setzt `done` + `confirmed`
  und gibt es frei.
- **AK-7** Pflicht-Rückgabe-Geräte (`abgeben_persoenlich`) erscheinen **nie** in der Selbst-Rückgabe.
- **AK-8** Foto ist nur für Admin oder den Eigentümer abrufbar, nie öffentlich aus `uploads/`.

## Offen / Folge
- DE-only zunächst (wie Geschwister-Standalone-Seiten); EN später.
- Optionaler `zhl_settings`-Key `self_return_notify_email` (Default SMTP_FROM) für die Team-Adresse.
- Codex-Cross-Review (Token-/Upload-/Mail-Pfad) vor Live.
