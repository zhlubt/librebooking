# SPEC-RUECKGABE-ANFRAGE — Rückgabetermin anfragen (Aushandlung → Mail + .ics), AK-9

**Stand:** 2026-07-02 · **Entwurf v3 (stark reduziert nach Paul-Feedback)** · noch nicht gebaut
Spiegelt [SPEC-EINFUEHRUNG-AUSHANDLUNG.md](SPEC-EINFUEHRUNG-AUSHANDLUNG.md) auf die **Rückgabe** —
**minus** §6-Reservierung, Zertifikat, Buchungsseiten-Injektion, Terminplaner-Buchung und Verfall.
Löst das offene Ticket **AK-9** aus [SPEC-RUECKGABE.md](SPEC-RUECKGABE.md).
Gehört zu [[zhl-buchungssystem-librebooking-pivot]] · Deploy-Details in [[zhl-media-staging-server]].

---

## 0. Scope-Entscheidung (Paul 2026-07-02) — WICHTIG

Ursprünglich war eine „decoupled Buchung mit injiziertem Rückgabe-Slot + Terminplaner-Buchung +
24-h-Verfall" geplant (v2, Codex-reviewt). **Paul hat den Umfang bewusst radikal reduziert:**

> „Es reicht mir, wenn alle Beteiligten — das haben wir schon — eine Mail mit einem Kalendereintrag
> bekommen."

**Dieses Feature liefert also NUR die Terminkoordination:** Team bietet Rückgabetermine an, Nutzer
wählt einen, **alle Beteiligten (Nutzer + entgegennehmende Person) bekommen eine Mail mit .ics-
Kalendereintrag.** Bausteine sind vorhanden (`ZhlIcs` + `ZhlTerminRequestEmail`).

**BEWUSST NICHT Teil dieses Features** (gestrichen ggü. v2):
- ❌ Terminplaner-Buchung des Rückgabetermins (kein neuer meet-Endpunkt).
- ❌ Injektion des Termins in die Buchungsseite / Verfügbarkeits-Floor-Umgehung.
- ❌ Automatische Geräte-Reservierung / Verlängerung bis Rückgabetag.
- ❌ 24-h-Verfall / Cron-Job / Verbrauch-Marker.

**Konsequenz (explizit, kein stiller Gap):** Die eigentliche **Geräte-Buchung wird durch diesen Flow
NICHT angelegt oder entsperrt.** Der Rückgabetermin ist reine Kalender-Koordination. Die Leihe legt
das **Team/Admin** an — Admins sind vom Pflicht-Rückgabe-Slot-Zwang ohnehin ausgenommen
(`ZhlBookPresenter`: `$returnMandatory = … && !$user->IsAdmin`), können also nach vereinbartem
Rückgabetermin normal für den Nutzer buchen. (Falls später gewünscht, dass der Nutzer nach der
Terminvereinbarung selbst buchen kann, ist das ein separates Folge-Ticket — hier ausgeklammert.)

## 1. Problem

Geräte mit **Pflicht-Rückgabe** (`zhl_uebergabe.rueckgabe='abgeben_persoenlich'`, z. B. Pocket 6K
res 22) verlangen beim Buchen einen persönlichen Rückgabetermin aus dem Terminplaner (Typ „Übergabe
Medien"). Reicht die Terminplaner-Verfügbarkeit nicht bis zum Rückgabetag (**verifiziert 2026-07-02:
„Übergabe Medien"-Slots enden am 24.08.2026**), zeigt `zhl-book.tpl` nur „Kein Rückgabetermin ab
deinem Ausleihende frei" und blockiert. Für die **Einführung** gibt es längst den Ausweg
„Einführungstermin anfragen" (Aushandlung + ICS) — der Rückgabe fehlt das Pendant.

## 2. Zielbild

Reframe des vorhandenen Aushandlungs-Flows auf `purpose='return'`:
1. **Anfrage:** Buchungsseite zeigt bei blockierter Pflicht-Rückgabe einen Button
   **„Rückgabetermin anfragen"** → `zhl-termin-anfrage.php?purpose=return&rid=…`. Nutzer nennt
   gewünschtes Nutzungsfenster + Projekt. Mail ans Team (CC Nutzer). **Kein Gerät reserviert.**
2. **Aushandlung:** Admin(s) tragen **1..n konkrete Rückgabetermine** ein (freie `datetime-local`),
   je mit **„wer nimmt die Rückgabe entgegen"** (Dropdown aller Admins, Default = aktueller Admin) +
   Notiz. „Angebote senden" → Token-Mail an den Nutzer. „Ablehnen" bleibt möglich.
3. **Auswahl:** Nutzer wählt per **login-freiem Token-Link** einen Termin (= Bestätigung).
4. **Ergebnis:** **.ics-Termineinladung + Mail an Nutzer + entgegennehmende Person.** Storno-Link für
   beide (CANCEL-.ics, gleiche UID). **Sonst passiert nichts** (keine Reservierung, kein Terminplaner).

## 3. Datenmodell (Migration `032_zhl_rueckgabe_purpose.sql`, idempotent)

Minimal — Wiederverwendung von `zhl_termin_request` + `zhl_termin_offer` (027/029):
- `zhl_termin_request` **ADD COLUMN IF NOT EXISTS `purpose VARCHAR(8) NOT NULL DEFAULT 'einf'`**
  (`'einf' | 'return'`). Bestehende Zeilen = `'einf'` → **kein Regress**. Das ist die **einzige**
  Schema-Änderung.
- **Keine** weiteren Spalten (kein `return_slot_*`, kein `tp_*`, kein Verbrauch-Marker — die Zeit
  steht am gewählten `zhl_termin_offer`).
- `zhl_termin_offer` unverändert: bei `purpose='return'` = Rückgabetermin; `instructor_uid` = wer die
  Rückgabe entgegennimmt (ICS-Organizer). `ics_uid`-Form `zhl-return-<req>-<offer>@media.zhl-ubt.de`.

Statuswerte wie gehabt (`open|offered|confirmed|declined|cancelled`).

## 4. Fluss (identisch zur Einführungs-Aushandlung, nur purpose-abhängige Texte + kein §6/Cert)

### 4.1 Anfrage (`zhl-termin-anfrage.php?purpose=return`)
- `tpl/zhl-book.tpl`: im Return-blocked-Zweig (heute nur der flache Hinweis) Button
  **„Rückgabetermin anfragen"** ergänzen (Muster = „Einführungstermin anfragen"), sichtbar nur wenn
  `Return.mandatory && Return.blocked && Fulfillment != 'hauspost'`.
- Anfrage-Seite `purpose=return`: Überschrift „Rückgabetermin anfragen", passender Hinweistext
  („bucht das Gerät nicht; das Team schlägt Rückgabetermine vor, du wählst einen; die Leihe selbst
  richtet das Team ein"). `ZhlTerminRequest::Create(..., purpose='return')`. Mail ans Team (CC Nutzer).

### 4.2 Admin (`zhl-termin-anfrage-admin.php`)
Identisch zur Einführung, `purpose`-abhängige Labels (Badge „Rückgabe"; „Rückgabetermin anbieten";
Dropdown „Rückgabe entgegennehmen (wer)"). Aktionen `offer` / `withdraw_offer` / `send_offers`
(blockiert ohne `open`-Angebot) / `decline` / `resend_ics` — **unverändert**, purpose-agnostisch.

### 4.3 Auswahl (`zhl-termin-auswahl.php?token=…`, login-frei) — atomar
1. **Angebot atomar claimen:** `UPDATE zhl_termin_offer SET status='chosen', ics_uid=@uid,
   chosen_at=@now WHERE id=@oid AND request_id=@rid AND status='open'`; affected=0 → Abbruch
   („Termin nicht mehr verfügbar").
2. **Request atomar claimen:** `UPDATE zhl_termin_request SET status='confirmed', chosen_offer_id=@oid,
   confirmed_at=@now WHERE accept_token=@tok AND status='offered'`; affected=0 → Offer-Claim
   zurückrollen (`chosen`→`open`) + Idempotenz-Zustand anzeigen.
3. Übrige `open`-Angebote → `withdrawn`.
4. **.ics-Einladung** (`METHOD:REQUEST`, `SEQUENCE:0`, SUMMARY „ZHL Rückgabe: <Gerät>") + Mail an
   **Nutzer + Entgegennehmer**. Mail-/ICS-Fehler kippt die Bestätigung nicht (Log+Audit,
   `resend_ics` nachholbar).
5. **Kein** §6, **kein** Zertifikat, **keine** Reservierung, **keine** Buchungsseiten-Injektion.
   Seite/Mail zeigen als Hinweis: „Ihr Rückgabetermin steht am <lokal>. Das ZHL-Team richtet die
   Ausleihe ein / bei Fragen antworten Sie auf diese Mail." (rein informativ, kein Deep-Link-Zwang).
- Idempotent: zweiter Klick zeigt den Zustand ohne Re-Versand.

### 4.4 Storno (`zhl-termin-storno.php?token=…`, login-frei)
- Aus `confirmed` atomar → `cancelled`; gewähltes Offer → `withdrawn`, `ics_sequence=1`;
  **CANCEL-.ics** (gleiche UID, `SEQUENCE:1`) + Mail an beide. **Im Terminplaner/Reservierung ist
  nichts abzusagen** (es wurde nie etwas gebucht).
- Aus `offered` (nichts bestätigt): Nutzer **zieht zurück** → `cancelled`, Offers `withdrawn`, kein ICS.

## 5. ICS
`lib/Application/Zhl/ZhlIcs.php` wiederverwenden; `purpose='return'` → SUMMARY „ZHL Rückgabe:
<Gerät>", `ORGANIZER`=Entgegennehmer, `ATTENDEE`=Nutzer+Entgegennehmer, UID `zhl-return-…`.
REQUEST/CANCEL + SEQUENCE 0/1 wie in der Einführungs-Spec.

## 6. Betroffene Dateien (klein)

| Datei | Änderung |
|---|---|
| `docs/zhl/migrations/032_zhl_rueckgabe_purpose.sql` | **neu** — nur `ADD COLUMN purpose` |
| `lib/Application/Zhl/ZhlTerminRequest.php` | `purpose` in Create/Read (Default 'einf') |
| `Web/zhl-termin-anfrage.php` + `Pages/ZhlTerminAnfragePage.php` + `Presenters/ZhlTerminAnfragePresenter.php` + `tpl/zhl-termin-anfrage.tpl` | `purpose=return`-Zweig: Texte, Nutzungsfenster |
| `Web/zhl-termin-anfrage-admin.php` | `purpose`-Badge + Labels |
| `Web/zhl-termin-auswahl.php` | `purpose`-Texte; **kein** Cert/§6 im return-Zweig, nur ICS+Mail |
| `Web/zhl-termin-storno.php` | `purpose`-Texte (Cancel = nur Status + CANCEL-ICS) |
| `lib/Application/Zhl/ZhlIcs.php` | `purpose`-abhängige SUMMARY/UID |
| `tpl/zhl-book.tpl` | „Rückgabetermin anfragen"-Button im Return-blocked-Zweig |

**Nicht mehr betroffen** (ggü. v2 gestrichen): `Presenters/ZhlBookPresenter.php` (keine Injektion),
`Jobs/zhl_return_request_expiry.php` + `Web/zhl-cron.php` (kein Verfall), `terminplaner_ubt/api/*`
(kein neuer meet-Endpunkt).

## 7. Sicherheit / Robustheit (wie SPEC-EINFUEHRUNG-AUSHANDLUNG §7)
- CSRF auf Admin-POSTs; Admin-Seite rollen-gated. `accept_token` 40 hex, DB-`UNIQUE`, ein Token für
  Auswahl+Storno; Autorisierung über **Request-Status**. Atomare Statusübergänge per conditional
  `UPDATE … WHERE status=<vor>` inkl. atomarem Offer-Claim (`WHERE status='open'`, Withdraw-Race).
- Mail-/ICS-Fehler kippt keine DB-Aktion (`resend_ics` nachholbar). Ausgaben/ICS RFC-escaped.
  AdHocCommand+Parameter, keine Präfix-kollidierenden Parameternamen.

## 8. Akzeptanzkriterien (EARS)
- **AK-1** *Wenn* die Pflicht-Rückgabe keinen freien Slot hat, *dann* zeigt die Buchungsseite den
  Button „Rückgabetermin anfragen".
- **AK-2** *Wenn* der Nutzer eine Rückgabe-Anfrage stellt, *dann* entsteht ein `zhl_termin_request`
  mit `purpose='return'` + Team/Nutzer-Mail; **kein Gerät reserviert**.
- **AK-3** *Wenn* ein Admin Rückgabetermine anbietet und sendet, *dann* erhält der Nutzer einen
  Token-Link mit allen `open`-Angeboten.
- **AK-4** *Wenn* der Nutzer einen Termin bestätigt, *dann* wird das Angebot atomar `chosen`, der
  Request `confirmed`, und Nutzer + Entgegennehmer erhalten **Mail + .ics** mit gleicher UID.
- **AK-5** *Wenn* Nutzer **oder** Admin storniert, *dann* wird der Request `cancelled` und beide
  erhalten eine CANCEL-.ics mit derselben UID (Termin weg in beiden Kalendern).
- **AK-6** *Wenn* der Bestätigen-Link doppelt/parallel ausgelöst wird, *dann* genau **ein** `chosen`,
  **eine** ICS-Mail (atomarer Offer- + Request-Claim).
- **AK-7** *Wenn* ein Admin ein Angebot zurückzieht, *dann* kann der Nutzer es nicht mehr bestätigen
  (atomarer Claim `WHERE status='open'`).
- **AK-8** *Niemals* legt der Flow eine Geräte-Reservierung oder Terminplaner-Buchung an (reine
  Terminkoordination; Regressions-Schutz gegen die alte Range-Buchung).
- **AK-9** Bestehende `purpose='einf'`-Anfragen (inkl. §6/Cert) funktionieren unverändert (kein
  Regress durch die `purpose`-Spalte / geteilte Seiten).

## 9. Test / Verifikation (media)
- DB-Backup (PHP-mysqli) **vor** Migration; Migration 032 idempotent (2× = no-op).
- E2E: res 22, Nutzungsende nach 24.08. → „Rückgabetermin anfragen" → Admin bietet 27.08. an →
  Token-Auswahl → **Mail + .ics** an Nutzer + Entgegennehmer (Anhang öffnet im Kalender) →
  Storno (aus offered / aus confirmed → CANCEL-ICS).
- Regress: eine `purpose='einf'`-Anfrage komplett durchspielen (Cert/§6 unverändert).

## 10. Offen / später
- Bundle mit Pflicht-Rückgabe (wie Einführung: Einzelgerät zuerst).
- Optional-Folge-Ticket: nach Terminvereinbarung dem Nutzer erlauben, die Leihe **selbst** zu buchen
  (Injektion/Entsperrung) — bewusst NICHT in v3.
- Verfall/Reminder — bewusst NICHT in v3 (Paul: erst wenn nötig).
