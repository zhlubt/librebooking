# SPEC-EINFUEHRUNG-AUSHANDLUNG — Wunschtermin = Einführungs-Terminwunsch mit Aushandlung

**Stand:** 2026-06-28 · Entwurf (Branch `feat/zhl-einfuehrung-aushandlung`) · **Codex-reviewed v2**
(4 Blocker eingearbeitet: UNIQUE-Token, persistierte `einf_reservation_ref`, atomare Bestätigung,
Storno nur aus `confirmed` + getrenntes „Zurückziehen" aus `offered`).
**Supersedet** den Buchungs-Teil von [SPEC-WUNSCHTERMIN.md](SPEC-WUNSCHTERMIN.md) (1-Klick-„Als Admin buchen").

---

## 1. Problem (warum der bisherige Stand falsch ist)

1. **Range-Buchung statt Termin.** Heute legt `zhl-termin-anfrage-admin.php` per „Als Admin buchen"
   eine Reservierung **09:00–17:00 über den GANZEN Wunsch-Zeitraum** an (`desired_start…desired_end`).
   Beim **Videostudio** (stundenweise Buchung, `booking_mode='slot'`) ist eine 7-Tage-Buchung fachlich
   falsch — getestet: Anfrage „30.06.–07.07." wurde als durchgehende Buchung eingetragen.
2. **Falsches mentales Modell.** Die Wunschtermin-Anfrage entsteht, wenn **kein passender
   Einführungs-Termin** frei ist (Buchungsseite: „Ist eine Einführung nötig, wird der gewählte Termin
   direkt im Terminplaner gebucht …"). Sie ist also ein **Wunsch nach einer EINFÜHRUNG**, nicht nach
   dem Gerät für X Tage. Texte/Formular suggerieren aber eine Geräte-Anfrage.
3. **Kein Aushandlungsprozess.** Es gibt nur *eine* Anfrage mit *einem* Status. Es fehlt: mehrere
   konkrete Terminangebote, mehrere anbietende Admins, Auswahl durch den Nutzer, Kalendereinladungen.

## 2. Zielbild (mit dem Nutzer abgestimmt, 2026-06-28)

- Die Anfrage ist ein **Einführungs-Terminwunsch**: „Wann möchten Sie Ihre Einführung für \<Gerät\>?"
- **Zwei Fälle:**
  - **Gerät gar nicht verfügbar** → ehrliche **Absage** („Ablehnen", wie gehabt).
  - **Gerät verfügbar, aber kein passender Einführungs-Slot** → **serviceorientierte Aushandlung.**
- **Aushandlung:** ein oder mehrere Admins tragen **1–n konkrete Termin-Angebote** ein, je mit
  **„wer macht die Einführung"** (Dropdown: **alle LibreBooking-Admins**, Default = aktueller Admin)
  und optionaler Notiz. Mehrere Admins dürfen mehrere Termine anbieten.
- **Auswahl:** der Nutzer bekommt eine Mail mit den Angeboten + **login-freiem Auswahllink**
  (Token). Er wählt **einen** Termin → das ist die **Bestätigung**.
- **Kalender:** bei Bestätigung bekommen **Nutzer + anbietender Admin** eine **.ics-Termineinladung**.
  Beide haben in der Mail einen **„Termin stornieren/ablehnen"-Link** → bei Klick wird der Termin
  **für alle Beteiligten abgesagt** (Cancellation-.ics, gleiche UID).
- **Geräte-Leihe bleibt SEPARAT.** Nach Bestätigung CTA an den Nutzer:
  „**Bitte buchen Sie jetzt noch Ihre gewünschte Ressource ab \<intro_end\> (wenn die Einführung
  zu Ende ist).**" → Deep-Link auf die normale Buchungsseite. Die Leih-Reservierung legt der Nutzer
  selbst an (er gilt nach der Einführung als eingeführt — Cert-Confirm, s.u.).
- **Reservierung der Ressource FÜR die Einführungs-Stunde** (damit das Studio während der Einführung
  belegt ist) wird über den **bestehenden** Mechanismus erzeugt — siehe §6.

## 3. Datenmodell (Migration `028_zhl_termin_offer.sql`, idempotent)

**`zhl_termin_request`** — additiv erweitern (MariaDB kennt kein `ADD COLUMN IF NOT EXISTS`
zuverlässig → idempotent über „Spalte vorhanden?"-Prüfung im SQL-Wrapper bzw. `try/catch` je ALTER):
- `accept_token       VARCHAR(40) NULL` — login-freier Auswahl-/Storno-Token (random, einmalig gesetzt).
  → **`UNIQUE KEY uq_accept_token (accept_token)`** (NULL-tolerant; MySQL/MariaDB erlauben mehrere NULLs).
- `chosen_offer_id    INT UNSIGNED NULL` — gewähltes Angebot (nach Bestätigung).
- `confirmed_at       DATETIME NULL`.
- `einf_reservation_ref VARCHAR(32) NULL` — **`reference_number` der nativen Einführungs-Reservierung**
  (§6), damit der Storno-Pfad genau diese Reservierung wieder absagen kann.
- `status`-Wertebereich erweitert: `open | offered | confirmed | declined | cancelled | booked`.
  - `open` = neu, noch keine Angebote raus · `offered` = ≥1 Angebot an Nutzer geschickt, wartet auf Wahl
  - `confirmed` = Nutzer hat gewählt, Termin steht · `declined` = Admin hat abgelehnt (Gerät nicht da)
  - `cancelled` = storniert (Nutzer zog Anfrage zurück **oder** bestätigter Termin wurde abgesagt)
  - `booked` = nur Altdaten (alter 1-Klick-Pfad), kein neuer Übergang dorthin.

**`zhl_termin_offer`** (neu):
```
id               INT UNSIGNED AUTO_INCREMENT PK
request_id       INT UNSIGNED NOT NULL          -- → zhl_termin_request.id
instructor_uid   MEDIUMINT UNSIGNED NOT NULL    -- WER die Einführung macht (ICS-Organizer)
instructor_name  VARCHAR(190) NOT NULL DEFAULT ''-- Snapshot „Vorname Nachname"
created_by_uid   MEDIUMINT UNSIGNED NOT NULL    -- WELCHER Admin den Termin eingetragen hat (Audit)
start_utc        DATETIME NOT NULL              -- konkreter Einführungs-Start (UTC)
end_utc          DATETIME NOT NULL              -- Einführungs-Ende (UTC)
note             VARCHAR(500) NULL
status           VARCHAR(12) NOT NULL DEFAULT 'open'  -- open | chosen | withdrawn
ics_uid          VARCHAR(80) NULL               -- stabile iCalendar-UID (gesetzt bei chosen)
ics_sequence     SMALLINT UNSIGNED NOT NULL DEFAULT 0 -- 0=REQUEST, 1=CANCEL
created_at       DATETIME NOT NULL
chosen_at        DATETIME NULL
withdrawn_at     DATETIME NULL
INDEX idx_request_status (request_id, status)
```
- **`instructor_uid` ≠ `created_by_uid`**: Admin A darf einen Termin für Admin B („Einführung macht B")
  eintragen. Der ICS-Organizer ist der **Instructor**; das Audit hält den Eintragenden fest.
- Die **ICS-UID** liegt am gewählten Angebot, damit Einladung (`REQUEST`, seq 0) und spätere Absage
  (`CANCEL`, seq 1) dieselbe UID + aufsteigende `ics_sequence` nutzen.

## 4. Fluss

### 4.1 Nutzer stellt Anfrage (`zhl-termin-anfrage.php`)
- Reframing: Überschrift „**Wunschtermin für eine Einführung anfragen**".
- Eingaben: **gewünschter Zeitraum/Zeitfenster** (von/bis bleibt — als grober Rahmen „ab wann
  brauche ich das Gerät / wann hätte ich Zeit für die Einführung"), Projekt-Titel, Freitext.
- Hinweis-Text klar: „Eine Anfrage reserviert das Gerät nicht. Das Team schlägt Ihnen konkrete
  **Einführungstermine** vor; Sie wählen einen aus."
- Mail ans Team (CC Nutzer) wie gehabt — Betreff/Body auf „Einführungs-Terminwunsch" umtexten.

### 4.2 Admin handelt aus (`zhl-termin-anfrage-admin.php`)
Pro offener Anfrage (Status `open`/`offered`):
- **Angebot hinzufügen** (POST `action=offer`): Felder Start (datetime-local), Dauer/Ende,
  Dropdown **„Einführung macht"** = `instructor_uid` (alle Admins, Default = aktueller Admin), Notiz.
  → Zeile in `zhl_termin_offer` (`open`), `created_by_uid` = aktueller Admin. CSRF, Validierung
  (Start in der Zukunft, Ende > Start; liegt der Termin außerhalb des Wunsch-Zeitfensters →
  weiche Warnung, kein harter Stopp). Anbieten nur aus Request-Status `open`/`offered`.
- **Angebots-Liste** mit „zurückziehen" (POST `action=withdraw_offer`, setzt `withdrawn` +
  `withdrawn_at`; nur eigene/jeder Admin — Audit über `created_by_uid`).
- **Angebote senden** (POST `action=send_offers`): **blockiert, wenn keine `open`-Angebote existieren**
  (kein `offered`-Request ohne wählbaren Termin). Sonst: `accept_token` erzeugen falls leer, Mail an
  den Nutzer mit allen `open`-Angeboten + Auswahllink → Request-Status `offered`. Re-sendbar.
- **Ablehnen** (POST `action=decline`, wie gehabt): Status `declined` + Mail. (Gerät nicht verfügbar.)
- **Die alte „Als Admin buchen"-Range-Buchung entfällt ersatzlos** (Regressions-Schutz AK-8).

### 4.3 Nutzer wählt (`zhl-termin-auswahl.php?token=…`, login-frei)
- Tokenisierte, ZHL-gestylte Seite (Muster wie Handover-Token-Seiten). Listet `open`-Angebote
  (Datum/Uhrzeit lokal, „Einführung mit \<Instructor\>", Notiz). Radio + „**Diesen Termin bestätigen**".
- **Bestätigung (POST, Token-gated) — atomare Reihenfolge gegen Doppel-Submit/Tabs/Withdraw-Race:**
  1. **Angebot re-prüfen**: gewähltes Angebot muss `status='open'` UND `request_id` = die Anfrage des
     Tokens sein. Sonst Abbruch mit „Dieser Termin ist nicht mehr verfügbar." (Admin hat zurückgezogen).
  2. **Request atomar claimen**: `UPDATE … SET status='confirmed', chosen_offer_id=@oid,
     confirmed_at=@now WHERE accept_token=@tok AND status='offered'`. **Affected rows = 0 → Abbruch**
     (bereits bestätigt/abgelaufen → Idempotenz-Anzeige, kein zweiter Versand).
  3. **Ressource reservieren** (§6, nur blockende Geräte). **Konflikt → Request auf `offered`
     zurücksetzen** (`chosen_offer_id=NULL`), Fehlermeldung „Slot leider inzwischen belegt"; **kein**
     `confirmed`-Request bleibt zurück.
  4. Gewähltes Angebot → `chosen` (+ `ics_uid`, `chosen_at`), übrige `open` → `withdrawn`;
     `einf_reservation_ref` am Request speichern (falls reserviert).
  5. **ICS-Einladung** (`METHOD:REQUEST`, `SEQUENCE:0`) als `.ics`-Anhang an **Nutzer + Instructor**.
  6. **Storno-Mail/-Link** an beide: `zhl-termin-storno.php?token=…` (`who=user|admin` nur Anzeige/Audit,
     **nie** als Berechtigung — Autorisierung ausschließlich über den Token).
  7. **Mail-Fehler kippt die Bestätigung NICHT**, wird aber als Warnung geloggt + audit-vermerkt; die
     Auswahlseite zeigt dann einen Hinweis „Termin steht, Einladung kommt ggf. separat" und der Admin
     kann über die Anfrage-Seite erneut senden (`action=resend_ics`).
  - **CTA Leih-Buchung:** Seite + Mail zeigen „Bitte buchen Sie jetzt noch Ihre gewünschte Ressource
    **ab \<intro_end lokal\>** (wenn die Einführung zu Ende ist)" mit Deep-Link
    `zhl-book.php?rid=…&start=…` (Start = Einführungs-Ende).
- **Idempotent**: zweiter Klick auf einen schon `confirmed`/`cancelled` Token zeigt den jeweiligen
  Zustand + (bei confirmed) den Leih-CTA — ohne erneuten Versand.

### 4.4 Storno (`zhl-termin-storno.php?token=…`, login-frei)
- Gleicher Token, Bestätigungs-Klick („Termin wirklich absagen?").
- **Nur aus `status='confirmed'`** atomar: `UPDATE … SET status='cancelled' WHERE accept_token=@tok
  AND status='confirmed'`. Affected rows = 0 → „nichts zu stornieren" (Zustand anzeigen).
- Gewähltes Angebot → `withdrawn`; `ics_sequence` auf 1.
- Verschickt **Cancellation-.ics** (`METHOD:CANCEL`, **gleiche `ics_uid`, `SEQUENCE:1`**) an
  **alle Beteiligten** (Nutzer + Instructor) → Termin verschwindet in beiden Kalendern.
- Falls `einf_reservation_ref` gesetzt ist (§6), wird **genau diese** native Reservierung über den
  bestehenden Cancel-Pfad ([ZhlBookingDetailPresenter](../../Presenters/ZhlBookingDetailPresenter.php))
  storniert.

> **Aus `offered`** (noch nichts bestätigt) kann der Nutzer die Anfrage über denselben Token
> **zurückziehen** → `status='cancelled'`, alle Angebote `withdrawn`, **kein ICS** (es war kein Termin
> bestätigt). Das ist der saubere Pfad statt „Storno aus offered" (Codex-Blocker).

## 5. ICS (`lib/Application/Zhl/ZhlIcs.php`)

- Baut VCALENDAR/VEVENT als Klartext: `UID` (stabil, gespeichert in `zhl_termin_offer.ics_uid`,
  Form `zhl-einf-<request_id>-<offer_id>@media.zhl-ubt.de`), `DTSTAMP` (jetzt, UTC), `DTSTART`/`DTEND`
  als UTC (`…Z`), `SUMMARY` „ZHL Einführung: \<Gerät\>", `DESCRIPTION`, `LOCATION` (aus Config/Notiz),
  `ORGANIZER` = **Instructor**, `ATTENDEE` (Nutzer + Instructor),
  `METHOD:REQUEST`+`STATUS:CONFIRMED`+`SEQUENCE:0` für die Einladung bzw.
  `METHOD:CANCEL`+`STATUS:CANCELLED`+`SEQUENCE:1` für die Absage.
  CRLF-Zeilenenden, Werte escaped (`\, ; \n`) und auf 75 Oktette gefaltet (RFC 5545).
- **SEQUENCE-Regel fix**: Einladung = 0, Absage = 1 (aus `zhl_termin_offer.ics_sequence`); keine
  weiteren Updates in v1 (keine Termin-Verschiebung — Verschieben = stornieren + neu anbieten).
- Versand: `ZhlTerminRequestEmail` ruft das geerbte `AddStringAttachment($ics, 'einfuehrung.ics')`
  (von [EmailService](../../lib/Email/EmailService.php) per `addStringAttachment` durchgereicht).
  **MIME**: möglichst `text/calendar; method=REQUEST|CANCEL; charset=UTF-8`, Dateiname `einfuehrung.ics`.
  Falls `AddStringAttachment` nur einen generischen Typ setzt, bleibt es bei `.ics`-Anhang (öffnet in
  allen Kalender-Apps) — **v1 akzeptiert den einfachen Anhang**; kein Outlook-Auto-RSVP. Die Absage
  läuft über unsere Storno-Links, nicht über Kalender-RSVP-Buttons.

## 6. Reservierung der Ressource für die Einführungs-Stunde

- „**Zur Einführung muss die Ressource auch gebucht werden können — bereits implementiert.**"
  Gemeint: `ZhlBookPresenter::reserveEinfuehrungOnResource()` legt für `booking_mode='slot'`
  (Studio, `einf_blockt_geraet`) eine native Geräte-Reservierung für die Einführungs-Stunde an.
- **v1-Entscheidung:** Bei Bestätigung wird — sofern das Gerät slot-/einführungs-blockend ist —
  die Ressource für `start_utc…end_utc` reserviert (Wiederverwendung des bestehenden Pfads, Owner =
  Nutzer, Admin-Session-Kontext beim Auswahl-Submit existiert nicht → **als Systemreservierung
  über `ZhlReservationFacade` mit Nutzer-uid**, analog adminBook, aber nur die EINE Stunde).
  Scheitert das (Konflikt), wird die Bestätigung mit klarer Meldung verhindert (Slot inzwischen weg).
- **Server-Invarianten (kein Vertrauen in POST):** die Reservierung wird **ausschließlich** gebildet
  aus serverseitig gelesenen Werten — Owner = `request.user_id`, Ressource = `request.resource_id`,
  Zeit = `offer.start_utc/end_utc` des **gewählten** Angebots, nur wenn das Gerät blockend ist.
  Keine Ressource/kein Owner/keine Zeit aus dem Request-Body. `reference_number` der Reservierung →
  `request.einf_reservation_ref` (für späteren Storno, §4.4).
- Für nicht-blockende Geräte (Einführung durch eine Person, Gerät nicht belegt): **keine**
  Ressourcen-Reservierung für die Einführung — nur Kalendertermin (`einf_reservation_ref` bleibt NULL).

## 7. Sicherheit / Robustheit

- **CSRF** auf allen Admin-POSTs; Admin-Seite rollen-gated (`IsAdmin|ResourceAdmin|ScheduleAdmin|GroupAdmin`).
- **Token**: 20 Byte `random_bytes` → 40 hex; **`UNIQUE`-Spalte** (DB-seitiger Kollisionsschutz);
  an genau eine Anfrage gebunden; kein Login nötig (Bearer-Charakter, wie die Handover-Token-Seiten).
  - **Bewusste Akzeptanz:** EIN Token für Auswahl **und** Storno (kein separater `cancel_token`).
    Begründung: friktionsfrei für die Zielgruppe; der wirksame Schutz ist der **Request-Status**
    (Bestätigen nur aus `offered`, Storno nur aus `confirmed`), nicht der Token-Zweck.
  - `who=user|admin` im Storno-Link ist **nur Anzeige/Audit**, **niemals** Berechtigung.
- **Statusübergänge atomar per conditional `UPDATE … WHERE status=<vorstatus>`** + Prüfung der
  affected rows (kein Doppel-Bestätigen/Tab-Race): Bestätigen nur aus `offered`; Storno nur aus
  `confirmed`; Zurückziehen nur aus `open`/`offered`; Ablehnen/Anbieten nur aus `open`/`offered`.
  Das gewählte **Angebot** wird beim Bestätigen zusätzlich auf `status='open'` re-geprüft.
- **Mail-Fehler kippt keine DB-Aktion** (try/catch); bei der **Bestätigung** wird ein gescheiterter
  ICS-Versand jedoch geloggt + audit-vermerkt und ist über `action=resend_ics` nachholbar (sonst
  stünde ein Termin ohne Einladung).
- Ausgaben escaped; ICS-Werte RFC-escaped (kein Header-/Newline-Injection über Notiz/Projekt/Name).
- Alle DB-Zugriffe über `AdHocCommand`+`Parameter` (keine String-Interpolation); keine
  Parameternamen, die Präfix eines anderen sind (LibreBooking ersetzt per String-Replace).

## 8. Betroffene Dateien

| Datei | Änderung |
|---|---|
| `docs/zhl/migrations/028_zhl_termin_offer.sql` | **neu** — Tabelle + ALTERs |
| `lib/Application/Zhl/ZhlTerminRequest.php` | + Offer-CRUD, Token, GetByToken, SetConfirmed/Cancelled, ListAdmins |
| `lib/Application/Zhl/ZhlIcs.php` | **neu** — ICS-Builder (REQUEST/CANCEL) |
| `Web/zhl-termin-anfrage-admin.php` | Umbau: Angebote statt Range-Buchung |
| `Web/zhl-termin-auswahl.php` | **neu** — login-freie Auswahlseite |
| `Web/zhl-termin-storno.php` | **neu** — login-freier Storno |
| `Presenters/ZhlTerminAnfragePresenter.php` | Reframing-Texte, Mail-Body |
| `Presenters/ZhlTerminRequestEmail.php` | unverändert (Attachment via Basisklasse) |
| `tpl/zhl-termin-anfrage.tpl` | Reframing als Einführungs-Terminwunsch |
| `tpl/zhl-book.tpl` (353–358) | Texte: Wunschtermin = Einführung |
| `Pages/ZhlTerminAnfragePage.php` | ggf. neue Bind-Felder |

## 9. Akzeptanzkriterien (EARS)

- **AK-1** *Wenn* ein Admin auf der Anfrage-Seite ein Angebot mit Start, Ende und Anbieter einträgt,
  *dann* erscheint es in der Angebots-Liste der Anfrage und in `zhl_termin_offer` (`open`).
- **AK-2** *Wenn* mehrere Admins mehrere Angebote zu einer Anfrage eintragen, *dann* werden **alle**
  `open`-Angebote dem Nutzer in der Auswahlmail/-seite angezeigt.
- **AK-3** *Wenn* der Nutzer über den Token-Link **einen** Termin bestätigt, *dann* wird genau dieses
  Angebot `chosen`, alle anderen `withdrawn`, der Request `confirmed`, und Nutzer **und** anbietender
  Admin erhalten eine .ics-Einladung mit identischer UID.
- **AK-4** *Wenn* Nutzer **oder** Admin den Storno-Link klickt und bestätigt, *dann* wird der Request
  `cancelled` und **beide** erhalten eine Cancellation-.ics mit derselben UID (Termin weg in beiden
  Kalendern).
- **AK-5** *Wenn* ein Termin bestätigt ist, *dann* zeigen Seite und Mail den CTA, die Ressource ab
  dem Einführungs-Ende selbst zu buchen (Deep-Link mit vorbelegtem Start).
- **AK-6** *Wenn* das Gerät stundenweise/einführungs-blockend ist, *dann* hält die Bestätigung die
  Ressource für die Einführungs-Stunde (native Reservierung); bei Konflikt schlägt die Bestätigung
  mit klarer Meldung fehl.
- **AK-7** *Wenn* das Gerät gar nicht verfügbar ist, *dann* kann der Admin die Anfrage mit Grund
  **ablehnen** (Status `declined` + Mail) — kein Termin-Zwang.
- **AK-8** *Niemals* legt der Admin-Button eine durchgehende Mehrtages-Reservierung an
  (Regressions-Schutz gegen das Ursprungsproblem).
- **AK-9** *Wenn* der Bestätigen-Link zweimal/parallel ausgelöst wird (Doppel-Submit, zwei Tabs),
  *dann* wird **genau ein** Angebot `chosen`, **eine** ICS-Mail versendet und **eine** Reservierung
  angelegt (atomarer Claim aus `offered`).
- **AK-10** *Wenn* ein Admin ein Angebot zurückzieht, *dann* kann der Nutzer es nicht mehr bestätigen
  (Re-Check `offer.status='open'`); die Bestätigung bricht mit klarer Meldung ab.
- **AK-11** *Wenn* die Ressourcen-Reservierung bei der Bestätigung am Konflikt scheitert, *dann*
  bleibt **kein** `confirmed`-Request zurück (Rücksetzen auf `offered`).
- **AK-12** *Wenn* ein bestätigter Termin storniert wird, *dann* wird auch die native
  Einführungs-Reservierung (`einf_reservation_ref`) abgesagt; CANCEL-ICS nutzt **dieselbe UID** und
  `SEQUENCE:1`.
- **AK-13** *Wenn* keine `open`-Angebote existieren, *dann* lässt sich „Angebote senden" nicht
  auslösen (kein `offered`-Request ohne wählbaren Termin).
- **AK-14** Der `accept_token` ist DB-`UNIQUE`, ≥160 Bit Entropie und nicht erratbar.

## 10. Offen / später

- Voller iCalendar-RSVP-Workflow (`METHOD:REPLY`, Annahme via Kalender-Button) statt Storno-Link.
- Auto-Erinnerung, wenn ein `offered`-Request X Tage ohne Nutzer-Auswahl bleibt.
- Bundle-Einführungen (derzeit Fokus Einzelgerät; Bundle weiter manuell).
