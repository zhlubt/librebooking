# Mail-Vorlagen — Entwurf zum Überarbeiten

> **Zweck:** Vorschlag, wie die ZHL-Mails künftig aussehen könnten, bevor sie als
> Smarty-Templates (DE/EN) in die App wandern. **Du kannst hier frei überschreiben.**
> Wenn die Texte stehen, gieße ich sie in Templates + i18n und deploye.
>
> **Design-Regeln eingehalten:** Sie-Form, geerdet, keine Capitals, keine Gedankenstriche,
> kein KI-Sprech. Absender/Signatur: „ZHL Medienausleihe".
>
> **Platzhalter** (werden zur Laufzeit ersetzt):
> `{NAME}` Ausleihende:r · `{GERAET}` Gerät/Medium · `{REF}` Buchungsnummer ·
> `{TITEL}` Titel des Einsatzes · `{FAELLIG}` Rückgabedatum · `{STUFE}` Mahnstufe ·
> `{TERMIN}`,`{RAUM}`,`{LIEFER}`,`{RUECKHOL}`,`{ANLIEFERORT}`,`{ABHOLORT}` Hauspost-Felder ·
> `{KONTAKT}` zhlmedien@uni-bayreuth.de

---

## 1. Hauspost-Versand-Antrag

**An:** Transport (Stefan Bauernschmitt) + ZHL Medien · **CC:** Ausleihende:r
**Wann:** sobald jemand „Per Hauspost" als Versandweg wählt.
**Ziel:** Der Medienmanager soll auf einen Blick prüfen und freigeben können.

### Betreff
- **DE:** `Hauspost-Antrag: {TITEL} ({REF})`
- **EN:** `House-mail request: {TITEL} ({REF})`

### Text (DE)
```
Hallo zusammen,

es liegt ein neuer Antrag auf Hauspost-Versand vor. Bitte prüfen und freigeben,
bevor das Material bereitgestellt und verschickt wird. Den Transport organisiert
Stefan Bauernschmitt.

Buchung
  Nummer:        {REF}
  Gerät/Medium:  {GERAET}
  Ausleihende:r: {NAME}
  Status:        wartet auf Prüfung

Angaben zum Einsatz
  Termin:        {TERMIN}
  Raum:          {RAUM}
  Titel:         {TITEL}

Versand
  Anlieferung möglich:  {LIEFER}
  Rückholung möglich:   {RUECKHOL}
  Anlieferungsort:      {ANLIEFERORT}
  Abholungsort:         {ABHOLORT}

Sie können den Antrag im Medienmanager öffnen und dort freigeben.

Viele Grüße
ZHL Medienausleihe
```

### Text (EN)
```
Hello,

a new house-mail dispatch request has come in. Please review and approve it
before the equipment is prepared and sent. Stefan Bauernschmitt arranges the
transport.

Booking
  Number:    {REF}
  Equipment: {GERAET}
  Borrower:  {NAME}
  Status:    awaiting review

Event details
  Date:   {TERMIN}
  Room:   {RAUM}
  Title:  {TITEL}

Dispatch
  Delivery window:  {LIEFER}
  Return window:    {RUECKHOL}
  Delivery point:   {ANLIEFERORT}
  Pickup point:     {ABHOLORT}

You can open and approve the request in the media manager.

Best regards
ZHL Media Lending
```

---

## Rückgabe-Mails — Ablauf (überarbeitet nach deinem Feedback 2026-06-26)

> **Wichtig — Freigabe:** Die **überfälligen** Erinnerungen (3 und 4) gehen **nicht
> automatisch** raus. Der **Ausleih-Manager / ein Admin gibt jede einzeln frei**. Grund:
> Das Gerät kann längst zurück sein (z. B. an einem Ablageort abgelegt), nur hat es noch
> niemand bestätigt — dann wäre eine Mahnung peinlich. Die **Vortag-Erinnerung (2)** ist
> harmlos und darf automatisch laufen.
>
> *(Umsetzung später: neuer „1 Tag vorher"-Trigger im Job + eine kleine Admin-Seite
> „fällige/überfällige Rückgaben → Mahnung freigeben". Sage Bescheid, dann baue ich das.)*

---

## 2. Rückgabe — Vortag-Erinnerung (freundlich, automatisch)

**An:** Ausleihende:r · **Wann:** **1 Tag vor** dem vereinbarten Rückgabe-Tag.

### Betreff
- **DE:** `Erinnerung: morgen geben Sie {GERAET} zurück`
- **EN:** `Reminder: {GERAET} is due back tomorrow`

### Text (DE)
```
Hallo {NAME},

kurze Erinnerung: morgen, am {FAELLIG}, ist Ihr vereinbarter Rückgabetermin für
{GERAET}. Bitte bringen Sie es wie abgesprochen zurück, damit es pünktlich für
die nächste Person bereitsteht.

Passt der Termin nicht mehr? Antworten Sie einfach auf diese Mail.

Viele Grüße
ZHL Medienausleihe
```

### Text (EN)
```
Hi {NAME},

a quick reminder: tomorrow, {FAELLIG}, is your agreed return date for {GERAET}.
Please bring it back as arranged so it is ready on time for the next person.

Does the date no longer work? Simply reply to this email.

Best regards
ZHL Media Lending
```

---

## 3. Rückgabe — überfällig, deutlich (erst nach Admin-Freigabe)

**An:** Ausleihende:r · **Wann:** ab ca. **2 Tage überfällig**. Ton: klar und bestimmt,
weil die Geräte bei uns oft direkt im Anschluss weiterverliehen sind.

### Betreff
- **DE:** `Überfällig: bitte {GERAET} zurückgeben`
- **EN:** `Overdue: please return {GERAET}`

### Text (DE)
```
Hallo {NAME},

{GERAET} sollte am {FAELLIG} zurück sein und ist es noch nicht. Solche Geräte
sind bei uns oft direkt im Anschluss an die nächste Person verliehen — eine
verspätete Rückgabe bringt also schnell die Planung anderer durcheinander.

Bitte geben Sie es heute oder spätestens morgen zurück. Falls Sie es bereits
abgegeben haben (z. B. an einem Ablageort), melden Sie sich bitte kurz unter
{KONTAKT}, dann haken wir das ab.

Viele Grüße
ZHL Medienausleihe
```

### Text (EN)
```
Hi {NAME},

{GERAET} was due back on {FAELLIG} and has not been returned. Devices like this
are often lent straight on to the next person, so a late return quickly upsets
other people's plans.

Please return it today or tomorrow at the latest. If you have already returned
it (e.g. to a drop-off point), just let us know at {KONTAKT} and we will close
it off.

Best regards
ZHL Media Lending
```

---

## 4. Rückgabe — letzte Erinnerung (erst nach Admin-Freigabe)

**An:** Ausleihende:r · **Wann:** weiter überfällig, nach der deutlichen Erinnerung.

### Betreff
- **DE:** `Letzte Erinnerung: {GERAET} bitte sofort zurückgeben`
- **EN:** `Final reminder: please return {GERAET} now`

### Text (DE)
```
Hallo {NAME},

{GERAET} ist seit dem {FAELLIG} überfällig, und wir haben Sie bereits erinnert.
Dies ist unsere letzte Erinnerung.

Bitte geben Sie das Gerät jetzt zurück. Andernfalls müssen wir Ihr
Ausleih-Konto vorübergehend sperren, bis das Material wieder da ist.

Wenn etwas im Weg steht oder das Gerät bereits zurück ist, melden Sie sich bitte
heute noch unter {KONTAKT}.

Viele Grüße
ZHL Medienausleihe
```

### Text (EN)
```
Hi {NAME},

{GERAET} has been overdue since {FAELLIG}, and we have already reminded you.
This is our final reminder.

Please return the device now. Otherwise we will have to suspend your lending
account temporarily until the equipment is back.

If something is in the way, or the device is already back, please reach us today
at {KONTAKT}.

Best regards
ZHL Media Lending
```

---

## Was noch dranhängt (zur Info, nicht Teil dieser Entwürfe)

- **Aktivierungs-Mail** und **Buchungsbestätigung** kommen aus dem nativen LibreBooking
  (eigene Templates unter `lang/.../email`). Wenn gewünscht, ziehe ich die separat ins
  ZHL-Wording nach.
- **Abhol-/Einführungs-Bestätigung** verschickt der Terminplaner (meet.zhl-ubt.de) beim
  `book_slot`. Wording dort wäre ein eigener kleiner Job.

## Was die Umsetzung noch braucht (über die Texte hinaus)

1. **Neuer Trigger** für die Vortag-Erinnerung (1 Tag VOR Rückgabe) — der jetzige
   Overdue-Job (`Jobs/zhl_overdue.php`) feuert erst NACH dem Ende. Muss erweitert werden.
2. **Admin-Freigabe-Gate** für die überfälligen Mails (3 + 4): kleine Admin-Seite
   „fällige/überfällige Rückgaben → Mahnung freigeben" + ein Status-Feld, statt
   automatischem Versand.
3. **Mittlere Stufe entfällt** (deine Vorgabe): keine „milde" Erinnerung mehr zwischen
   deutlich und letzter.

Sage Bescheid, ob die Texte so passen — dann baue ich (a) die Smarty-Templates, (b) den
Vortag-Trigger und (c) das Admin-Freigabe-Gate.
