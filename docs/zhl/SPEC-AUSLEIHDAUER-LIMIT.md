# SPEC-AUSLEIHDAUER-LIMIT

**Status:** v2 — Codex-reviewt (3 Blocker + 5 „Sollte" eingearbeitet, siehe §9)
**Branch:** `feat/zhl-ausleihdauer-limit`
**Kontext:** LibreBooking-ZHL-Fork, media.zhl-ubt.de. Ergänzt das Buchungs-Feature
([ZhlBookPresenter.php](../../Presenters/ZhlBookPresenter.php), [tpl/zhl-book.tpl](../../tpl/zhl-book.tpl)).
Verwandt mit [[zhl-rueckgabe-termin-feature]] (Abholung/Rückgabe-Modell) und der Token-/Aushandlungs-
Infrastruktur aus SPEC-EINFUEHRUNG-AUSHANDLUNG.

---

## 1. Ziel & Problem

Geräteausleihen sollen **standardmäßig auf eine maximale Nutzungsdauer** begrenzt werden (Default 14 Tage),
damit Geräte nicht wochenlang einzeln blockiert werden. Gleichzeitig soll das System **flexibel** bleiben:

1. **Toleranz** für die Übergabe-Logistik: Wenn nur Abhol-/Rückgabetermine außerhalb des Nutzungsfensters
   frei sind, soll die Buchung trotzdem gehen, solange der Versatz pro Seite klein bleibt (Default ≤ 5 Tage).
2. **Ausnahme-Anfrage:** Wer die Grenze überschreiten muss, kann eine **begründete Anfrage** stellen
   („Ich habe gesehen, dass das nicht geht, aber Argument X — ginge es doch?"). Ein Admin genehmigt oder
   lehnt ab; bei Genehmigung bucht der Nutzer **selbst** per login-freiem Token-Link (Limit für genau diese
   eine Reservierung aufgehoben).

**Vorbedingung (immer):** Das Gerät muss im gewünschten Fenster **verfügbar** sein. Die Ausnahme hebt
**nur** das Dauer-Limit auf, **nie** die native Konfliktprüfung (Doppelbuchung bleibt ausgeschlossen).

---

## 2. Begriffe & Messung

Im POST-Handler liegen bereits vor ([ZhlBookPresenter.php:437-475](../../Presenters/ZhlBookPresenter.php#L437-L475)):

| Größe | Bedeutung |
|---|---|
| `$beginDate/$beginTime` → `$endDate/$endTime` | **Nutzung** (fachliche Nutzungszeit, vom Nutzer im Kalender gewählt) |
| `$reservBeginDate/$reservBeginTime` | reservierter Start (ggf. **vorgezogen** wegen persönlicher Abholung) |
| `$reservEndDate/$reservEndTime` | reserviertes Ende (ggf. **verlängert** bis Rückgabetag) |
| Abhol-Slot / Rückgabe-Slot | separate Terminplaner-Termine (Tag der Abholung / Rückgabe) |

**Drei Maße (in ganzen Tagen):**

> **Wichtig (Codex SOLLTE-1):** Im Tagesmodus aus den **rohen Tagesfeldern** `$dayStartRaw`/`$dayEndRaw`
> rechnen, **nicht** aus dem technischen `$beginDate`/`$endDate` — letzteres wird bei Ganztags-Layout per
> „endNextDay" auf den Folgetag gesetzt ([ZhlBookPresenter.php:195-198](../../Presenters/ZhlBookPresenter.php#L195-L198))
> und würde sonst einen Extratag zählen. Gleiches gilt für `P_nach` (gegen `$dayEndRaw`).

- **Nutzungsdauer** `D_nutz` = Differenz **Enddatum − Startdatum** der Nutzung.
  - Tagesmodus: `D_nutz = dayEndRaw − dayStartRaw` (Tagesdifferenz). Beispiel **5.7.→19.7. ⇒ 14**.
    (Zählweise „Nächte", wie Hotel-Check-in/-out.)
  - Stundenmodus (Studio): `D_nutz = ceil((endDateTime − beginDateTime) / 24h)` (3-h-Buchung ⇒ 1).
- **Puffer vorne** `P_vor` = Tage zwischen **Abholtag** und **Nutzungsstart-Tag** (`$dayStartRaw`).
  - Keine persönliche Abholung (Ablageort/nicht nötig/Hauspost) ⇒ `P_vor = 0`.
  - **„Zusammen"-Modus** (Codex SOLLTE-2, `handover_mode='zusammen'`,
    [ZhlBookPresenter.php:236-238](../../Presenters/ZhlBookPresenter.php#L236-L238)): der **Einführungs-Slot
    ist** der Abholtermin → `P_vor` zählt gegen den **Einführungs-Slot-Tag**.
- **Puffer hinten** `P_nach` = Tage zwischen **Nutzungsende-Tag** (`$dayEndRaw`) und **Rückgabetag**. Keine
  persönliche Rückgabe ⇒ `P_nach = 0`. Bei `rueckgabe='abgeben_persoenlich'` (Reservierung ohnehin bis
  Rückgabetag verlängert) wird `P_nach` gegen das **Nutzungsende** gemessen, **nicht** gegen das verlängerte
  reservierte Ende (sonst doppelt gezählt).

**Regel (erfüllt = Buchung erlaubt):**

```
D_nutz  ≤  max_nutzung_tage      (Default 14)
P_vor   ≤  max_puffer_tage       (Default 5)   ← beide Seiten gegen DENSELBEN Wert
P_nach  ≤  max_puffer_tage       (Default 5)
```

Es gibt **einen** Puffer-Grenzwert; er wird gegen **jede** Seite einzeln geprüft (äquivalent:
`max(P_vor, P_nach) ≤ max_puffer_tage`). (Codex SOLLTE-5)

Beispiel aus der Anforderung: Nutzung 5.7.–19.7. (`D_nutz=14` ✓), Abholung 1.7. (`P_vor=4` ✓),
Rückgabe 23.7. (`P_nach=4` ✓) ⇒ **erlaubt**, obwohl das Gerät faktisch 1.7.–23.7. (22 Tage) blockiert ist.

> **Akzeptiertes Risiko (Paul):** Nutzer könnten die Nutzungs-Daten künstlich kürzen, um die Grenze zu
> umgehen. Das nehmen wir bewusst in Kauf — das Feature ist Steuerung, kein harter Schutz.

---

## 3. Konfiguration (System-Default + Gerät-Override)

**Global** (Tabelle `zhl_settings`, via [ZhlSettings](../../lib/Application/Zhl/ZhlSettings.php)):

| Key | Default | Bedeutung |
|---|---|---|
| `ausleih_max_nutzung_tage` | `14` | max. Nutzungsdauer (Tage), system-weit |
| `ausleih_max_puffer_tage`  | `5`  | max. Versatz Abholung/Rückgabe je Seite (Tage) |
| `ausleih_ausnahme_empfaenger` | `zhlmedien@uni-bayreuth.de` | Mail-Empfänger für neue Ausnahme-Anfragen |
| `ausleih_ausnahme_gueltig_tage` | `21` | Gültigkeit eines genehmigten Token-Grants (Tage) |

**Pro Gerät** (neue, nullbare Spalten auf `zhl_uebergabe`): `max_nutzung_tage INT NULL`,
`max_puffer_tage INT NULL`. `NULL` ⇒ globaler Default. Gepflegt in
[zhl-uebergabe-admin.php](../../Web/zhl-uebergabe-admin.php) (zwei neue Felder mit Platzhalter „Standard").

Auflösung pro Buchung: `effektiv = COALESCE(gerät, global_default)`.

---

## 4. Durchsetzung (Server = Autorität, Client = Komfort)

### 4.1 Server-Pre-Check (autoritativ)
In `ZhlBookPresenter::HandlePost`, **unmittelbar vor** dem Reserve-Aufruf
([ZhlBookPresenter.php:473-480](../../Presenters/ZhlBookPresenter.php#L473-L480)), nachdem Abhol-/Rückgabe-
Slots und `$reservBegin*/$reservEnd*` aufgelöst sind:

1. **Admins** (`$user->IsAdmin`) werden übersprungen (konsistent mit
   [ZhlBundleBookPresenter.php:421](../../Presenters/ZhlBundleBookPresenter.php#L421)).
2. `D_nutz`, `P_vor`, `P_nach` berechnen; effektive Limits laden. **Bezugs-Ressource = die ursprünglich
   angeklickte `$rid`** — also **vor** der Pool-Zuteilung
   ([ZhlBookPresenter.php:455-470](../../Presenters/ZhlBookPresenter.php#L455-L470)). (Codex BLOCKER-3)
3. Liegt ein **gültiger Ausnahme-Grant** vor (Token aus POST, siehe §6), geprüft per
   `IsValidFor($token, $userId, $origRid, $actualBeginUtc, $actualEndUtc)` ⇒ Limit-Check übersprungen.
   **Reihenfolge gegen Race (Codex BLOCKER-1):** Grant **atomar VOR** dem Reserve-Save auf `used` setzen
   (Conditional-Update + Nonce); schlägt der Save danach fehl, Grant wieder auf `approved` **zurücksetzen**.
   So kann derselbe Grant nicht von zwei parallelen POSTs doppelt eingelöst werden.
4. Sonst: Bei Verletzung `bindForm(...)` mit **konkreter** Meldung (welches der drei Maße, Ist vs. Limit)
   **und** Link „Ausnahme anfragen (mit Begründung)" → öffnet [zhl-dauer-ausnahme.php](../../Web/zhl-dauer-ausnahme.php)
   in **neuem Tab** (`target="_blank" rel="noopener"`), Fenster-Parameter vorbefüllt.

> Der Check sitzt bewusst als **ZHL-Pre-Check** (nicht als globale `IReservationValidationRule`), weil die
> Puffer-/Nutzungs-Trennung ZHL-spezifisch ist und Admins/Nicht-ZHL-Pfade nicht betroffen sein sollen.

### 4.3 Andere Save-Pfade & Defense-in-Depth (Codex SOLLTE-3)
Die nuancierte 14/5-Logik greift **nur** im ZHL-Buchungspfad (`zhl-book.php` / `zhl-bundle-book.php`). Native
Save-Pfade (Dashboard-Ajax `ReservationSavePage`, WebServices `ReservationSaveController`) durchlaufen **nur**
die native `ResourceMaximumDurationRule` (`resources.max_duration`).
- **Annahme/Scope-Grenze:** Reguläre ZHL-Nutzer buchen diese Geräte ausschließlich über die ZHL-UI; die
  nativen Reservierungs-Seiten sind für sie in dieser Installation nicht der Weg.
- **Backstop (empfohlen):** Trotzdem pro Gerät ein **großzügiges** natives `resources.max_duration` setzen
  (z. B. `max_nutzung_tage + 2·max_puffer_tage` Tage), damit selbst über native Pfade keine absurd langen
  Buchungen entstehen. Der ZHL-Pre-Check bleibt die feine Regel, die native Rule der harte Deckel. Dieser
  Backstop ist **kein** Ersatz für den Pre-Check (er kennt die Puffer-Trennung nicht).

### 4.2 Client-Vorvalidierung (nice-to-have)
JS auf der Buchungsseite zeigt früh einen Hinweis, sobald die gewählte Spanne die Grenze reißt
(Server bleibt die Wahrheit). Keine harte Sperre clientseitig.

---

## 5. Bundle-Buchung
[ZhlBundleBookPresenter](../../Presenters/ZhlBundleBookPresenter.php) nutzt heute schon
`bundleMaxDurationSeconds` (kleinste `max_duration` der Bundle-Geräte, Admin-Bypass). Analog: das
Nutzungs-Limit eines Bundles = **kleinstes** effektives `max_nutzung_tage` über alle Bundle-Geräte; Puffer-
Limit = kleinstes `max_puffer_tage`. Ausnahme-Anfrage für Bundles: **out of scope** dieser Iteration
(weiterhin manuell), wird im Fehlertext benannt.

**Bundle-Nachphase (Codex SOLLTE-4):** Bundles können eine zweite native „After"-Reservierung erzeugen
([ZhlBundleBookPresenter.php:530-543](../../Presenters/ZhlBundleBookPresenter.php#L530-L543)). Diese gehört
logistisch zur **selben** Ausleihe (Rückgabe-/Anschluss-Fenster) und ist **kein** separater Nutzungszeitraum
→ sie wird vom Nutzungs-Limit **nicht** separat erfasst. Maßgeblich für `D_nutz` ist das fachliche
Nutzungsfenster der Hauptphase.

---

## 6. Ausnahme-Anfrage (Token-Flow)

Eigene, von `zhl_termin_request` (jetzt Einführungs-Semantik) **getrennte** Kette.

### 6.1 Datenmodell — Migration `031_zhl_dauer_ausnahme.sql`
```sql
CREATE TABLE zhl_dauer_ausnahme (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  user_id           INT NOT NULL,
  resource_id       INT NOT NULL,
  requested_begin_utc DATETIME NOT NULL,   -- gewünschte Nutzung (kann Limit überschreiten)
  requested_end_utc   DATETIME NOT NULL,
  nutzung_tage      INT NOT NULL,          -- Snapshot zum Anfragezeitpunkt (informativ)
  puffer_vor_tage   INT NOT NULL DEFAULT 0,
  puffer_nach_tage  INT NOT NULL DEFAULT 0,
  reason            TEXT NOT NULL,         -- Begründung des Nutzers
  status            VARCHAR(16) NOT NULL DEFAULT 'open', -- open|approved|declined|used|expired
  grant_token       VARCHAR(40) NULL,
  claim_nonce       VARCHAR(40) NULL,      -- für atomares single-winner Claim/Release
  approved_nutzung_tage INT NULL,          -- vom Admin gewährte Obergrenze (NULL=wie angefragt)
  approved_puffer_tage  INT NULL,
  approved_until_utc DATETIME NULL,         -- Grant-Ablauf
  admin_note        TEXT NULL,
  handled_by        INT NULL,
  handled_at        DATETIME NULL,
  used_at           DATETIME NULL,
  used_reference_number VARCHAR(40) NULL,  -- die letztlich gebuchte Reservierung
  created_at        DATETIME NOT NULL,
  UNIQUE KEY uq_grant_token (grant_token),
  KEY idx_status (status),
  KEY idx_user_resource (user_id, resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 6.2 Klasse `lib/Application/Zhl/ZhlDauerAusnahme.php`
- `Create`, `ListOpen`, `Get`, `GetByToken`, `Approve` (setzt Token + Grenzen + Ablauf, status=approved),
  `Decline`.
- `IsValidFor($token, $userId, $resourceId, $actualBeginUtc, $actualEndUtc)` → bool. (Codex BLOCKER-2)
  Prüft **alle**: status=`approved`, `approved_until_utc` nicht abgelaufen, `user_id` == `$userId`,
  `resource_id` == `$resourceId` (= ursprünglich angeklickte `$rid`, §4.2), und **Fenster ⊆ genehmigt**:
  `$actualBeginUtc ≥ requested_begin_utc` **und** `$actualEndUtc ≤ requested_end_utc`
  (bzw. ⊆ der vom Admin ggf. enger gesetzten `approved_*`-Grenzen). Kein „Token = Freifahrt für beliebige
  Fenster".
- `ClaimGrant($token, $userId, $resourceId)` → bool (single-winner, atomar; **VOR** dem Reserve-Save):
  `UPDATE zhl_dauer_ausnahme SET status='used', claim_nonce=@n, used_at=@now WHERE grant_token=@t
  AND status='approved' AND user_id=@u AND resource_id=@r`, danach **Read-after-Nonce** (mysqli trennt nach
  Execute → kein `affected_rows`; gewonnen genau dann, wenn der Re-Read `claim_nonce==@n` zeigt). Pattern
  identisch zur Einführungs-Aushandlung (`ClaimOffer`/`ClaimRequest`).
- `ReleaseGrant($token, $nonce)` → setzt `status='approved'`, `claim_nonce=NULL`, `used_at=NULL` **nur**
  `WHERE grant_token=@t AND claim_nonce=@nonce` (Rückgabe nach fehlgeschlagenem Save — gibt nur den eigenen
  Claim frei).
- `SetUsedReference($token, $refNumber)` → nach erfolgreichem Save die Reservierungsnummer nachtragen.

Migration `031` bekommt dafür zusätzlich `claim_nonce VARCHAR(40) NULL`.

### 6.3 Ablauf
1. **Nutzer** scheitert am Limit ⇒ Button „Ausnahme anfragen" (neuer Tab) → `zhl-dauer-ausnahme.php`
   (SecurePage, eingeloggt; Gerät + Fenster aus Query). Formular: Begründung (Pflicht), Fenster anzeigbar/
   anpassbar. POST ⇒ `Create` (status=open). Mail an `ausleih_ausnahme_empfaenger`.
2. **Admin** [zhl-dauer-ausnahme-admin.php](../../Web/zhl-dauer-ausnahme-admin.php): offene Anfragen,
   **Genehmigen** (optional engere Grenzen setzen) / **Ablehnen** + Notiz.
   - Genehmigen ⇒ `grant_token = bin2hex(random_bytes(20))`, `approved_until_utc = now + ausleih_ausnahme_gueltig_tage`,
     status=approved. Mail an Nutzer mit login-freiem Link (neuer Tab) →
     `zhl-dauer-ausnahme-buchen.php?token=…`.
   - Ablehnen ⇒ status=declined, Mail mit Admin-Notiz.
3. **Nutzer** öffnet den Link: `zhl-dauer-ausnahme-buchen.php` validiert den Token (approved, nicht
   abgelaufen, nicht verbraucht) und **leitet in die Buchungsseite** (`zhl-book.php`) weiter — Fenster
   vorbefüllt, `ausnahme_token` als Hidden mitgegeben. Nicht eingeloggt ⇒ Login, dann weiter. Token-User ≠
   eingeloggter User ⇒ Hinweis/Abbruch.
4. **Buchung:** `HandlePost` erkennt `ausnahme_token`, ruft `IsValidFor($token,$user,$origRid,$actualBegin,
   $actualEnd)` ⇒ bei `true` Limit-Check übersprungen. Ablauf gegen Race (Codex BLOCKER-1):
   1. `ClaimGrant(...)` **vor** dem Reserve-Save (atomar auf `used`). Verloren ⇒ klarer Hinweis
      („bereits eingelöst"), kein Save.
   2. Reserve-Save ausführen.
   3. Erfolg ⇒ `SetUsedReference($token, $ref)`. Fehler (z. B. Konflikt) ⇒ `ReleaseGrant($token,$nonce)`
      (zurück auf `approved`, bis Ablauf wiederverwendbar) und Fehlermeldung.

### 6.4 Sicherheit
- `grant_token` = Bearer, 40 Hex (20 Byte Entropie), `UNIQUE`. Gebunden an `user_id` **und** `resource_id`
  (= ursprünglich angeklickte `$rid`; Pool-Zuteilung darf nur **gleichtypige** Einheiten treffen, die per
  Definition dieselben Limits/Regeln haben → Grant bleibt gültig, §4.2 / Codex BLOCKER-3).
- **Single-use** (atomar via Conditional-Update + Read-after-Nonce **VOR** dem Save; kein `affected_rows`, da
  mysqli nach Execute trennt). Save-Fehler ⇒ `ReleaseGrant` gibt **nur den eigenen** Claim (per Nonce) frei.
- **Fenster-Bindung:** gebuchtes Nutzungsfenster muss ⊆ `requested_*`/`approved_*` sein (Begin ≥, Ende ≤);
  in `IsValidFor` mit echten Begin/Ende geprüft — kein „Token = Freifahrt für beliebige Fenster".
- **Ablauf:** `approved_until_utc`; abgelaufen ⇒ Check verweigert (status ggf. lazy auf `expired`).
- Token-Seiten login-frei nur zum **Weiterleiten**; die eigentliche Buchung bleibt SecurePage + CSRF.

---

## 7. Akzeptanzkriterien (EARS)

- **AK-1** *Wenn* ein Nicht-Admin eine Buchung mit `D_nutz > effektivem max_nutzung_tage` absendet, *dann*
  wird die Reservierung abgelehnt und eine Meldung mit Ist-Dauer, Limit und Link zur Ausnahme-Anfrage
  (neuer Tab) gezeigt — **keine** Reservierung entsteht.
- **AK-2** *Wenn* `D_nutz ≤ Limit`, *aber* `P_vor > max_puffer_tage` *oder* `P_nach > max_puffer_tage`,
  *dann* wird ebenso abgelehnt, mit Benennung der verletzten Puffer-Seite.
- **AK-3** *Wenn* `D_nutz ≤ Limit ∧ P_vor ≤ Limit ∧ P_nach ≤ Limit`, *dann* läuft die Buchung normal durch
  (Beispiel 5.7.–19.7. mit Abholung 1.7./Rückgabe 23.7. **gelingt**).
- **AK-4** *Wenn* der Buchende **Admin** ist, *dann* greift das Limit nicht.
- **AK-5** *Wenn* ein Gerät eigene `max_nutzung_tage`/`max_puffer_tage` gesetzt hat, *dann* gelten diese
  statt der globalen Defaults.
- **AK-6** *Wenn* ein Nutzer eine Ausnahme-Anfrage absendet, *dann* entsteht ein `open`-Datensatz und eine
  Mail an den konfigurierten Empfänger; der Nutzer sieht eine Bestätigung.
- **AK-7** *Wenn* ein Admin eine Anfrage genehmigt, *dann* erhält der Nutzer eine Mail mit login-freiem
  Buchungs-Link; der Token ist an User+Gerät gebunden und hat ein Ablaufdatum.
- **AK-8** *Wenn* der Nutzer über einen gültigen Grant bucht und das Fenster ⊆ genehmigt ist, *dann* wird
  das Limit übersprungen und der Grant nach erfolgreicher Reservierung als `used` markiert (nicht erneut
  nutzbar).
- **AK-9** *Wenn* zwei Buchungsversuche denselben Grant gleichzeitig einlösen, *dann* gewinnt **genau einer**
  (atomar); der andere erhält einen klaren Hinweis.
- **AK-10** *Wenn* ein Grant abgelaufen oder bereits verbraucht ist, *dann* wird er nicht akzeptiert.
- **AK-11** *Unabhängig* von einem Grant bleibt die native Verfügbarkeits-/Konfliktprüfung wirksam — ein
  belegtes Gerät kann **nicht** über die Ausnahme gebucht werden.
- **AK-12** *Wenn* ein Admin ablehnt, *dann* status=declined und der Nutzer erhält die Admin-Notiz per Mail.
- **AK-13** *Wenn* eine Tagesbuchung über ein Ganztags-Layout endet (technisches `$endDate` = Folgetag wegen
  „endNextDay"), *dann* zählt `D_nutz` trotzdem aus `dayEndRaw − dayStartRaw` (kein Phantom-Extratag).
- **AK-14** *Wenn* eine Stunden-Buchung (Studio) z. B. 3 h dauert, *dann* `D_nutz = 1` (≤ Limit).
- **AK-15** *Wenn* `rueckgabe='abgeben_persoenlich'` die Reservierung über das Nutzungsende hinaus verlängert,
  *dann* misst `P_nach` gegen das **Nutzungsende**, nicht gegen das verlängerte reservierte Ende.
- **AK-16** *Wenn* `handover_mode='zusammen'` (Einführung = Abholung), *dann* zählt `P_vor` gegen den
  Einführungs-Slot-Tag.
- **AK-17** *Wenn* ein Grant-gebundenes Gerät per Pool-Fallback auf eine **gleichtypige** Einheit ausweicht,
  *dann* bleibt der Grant gültig (gleiche Limits); ein Wechsel auf einen **anderen Typ** findet nicht statt.

---

## 8. Betroffene Dateien (geplant)

**Neu:**
- `docs/zhl/migrations/031_zhl_dauer_ausnahme.sql` (+ Spalten auf `zhl_uebergabe`, + `zhl_settings`-Seeds)
- `lib/Application/Zhl/ZhlDauerAusnahme.php`
- `Web/zhl-dauer-ausnahme.php` (+ Page/Presenter/tpl) — Nutzer-Anfrageformular
- `Web/zhl-dauer-ausnahme-admin.php` (+ Page/Presenter/tpl) — Admin-Bearbeitung
- `Web/zhl-dauer-ausnahme-buchen.php` — Token → Weiterleitung in die Buchung

**Geändert:**
- `Presenters/ZhlBookPresenter.php` — Pre-Check + Grant-Einlösung in `HandlePost`; Limit-Helfer
- `tpl/zhl-book.tpl` — Fehlertext/Link, Client-Hinweis, `ausnahme_token`-Hidden
- `Presenters/ZhlBundleBookPresenter.php` — Bundle-Nutzungslimit (kleinstes Geräte-Limit)
- `Web/zhl-uebergabe-admin.php` (+ Presenter/tpl) — zwei Override-Felder pro Gerät
- ZHL-Mail-Versand (vorhandenes Muster) — drei Mails: an Empfänger (neue Anfrage), an Nutzer
  (genehmigt/abgelehnt)

---

## 9. Review-Historie & geklärte Punkte
**Codex-Review v1 (2026-06-30):** 3 Blocker + 5 „Sollte" — **alle in v2 eingearbeitet**:
- BLOCKER-1 (Race beim Grant): atomares Claim **vor** Save + `ReleaseGrant` bei Fehler (§4.2 Schritt 3, §6.3/4).
- BLOCKER-2 (Fenster-Bindung): `IsValidFor` mit echtem Begin/Ende, Fenster ⊆ genehmigt (§6.2, §6.4).
- BLOCKER-3 (Pool-Fallback): Grant gegen **ursprünglich angeklickte** `$rid` (vor Pool-Switch); gleichtypige
  Pool-Einheit erbt Limits (§4.2, §6.4, AK-17).
- SOLLTE-1 (Tag-Zählung aus `dayStartRaw/dayEndRaw`), SOLLTE-2 („zusammen"-Modus in `P_vor`),
  SOLLTE-3 (andere Save-Pfade: Scope-Annahme + nativer Backstop, §4.3), SOLLTE-4 (Bundle-Nachphase, §5),
  SOLLTE-5 (ein Puffer-Wert, pro Seite geprüft).

**Geklärte Entscheidungen:**
1. `D_nutz` = Nächte-Logik in beiden Modi (Tag: Tagesdifferenz; Stunde: `ceil(h/24)`). ✓
2. `P_nach` bei `abgeben_persoenlich` gegen **Nutzungsende**. ✓
3. Grant-Fenster: **⊆** genehmigte Begin/Ende (Teilfenster erlaubt, nicht exaktes Matching). ✓
4. Ablauf: **lazy** (Check verweigert ab `approved_until_utc`); optionaler Aufräum-Job kann später
   `status='expired'` setzen (nicht funktionskritisch).

## 10. Offen für nächste Iteration (bewusst out of scope)
- Ausnahme-Anfrage für **Bundles** (vorerst manuell).
- Optionaler Cron, der abgelaufene Grants auf `expired` setzt (kosmetisch/Reporting).
