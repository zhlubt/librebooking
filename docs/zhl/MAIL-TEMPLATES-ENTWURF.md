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

## 2. Rückgabe-Erinnerung, Stufe 1 (freundlich)

**An:** Ausleihende:r · **Wann:** ca. 1 Tag nach geplantem Rückgabe-Ende.

### Betreff
- **DE:** `Erinnerung: Rückgabe von {GERAET}`
- **EN:** `Reminder: please return {GERAET}`

### Text (DE)
```
Hallo {NAME},

das Gerät {GERAET} war bis zum {FAELLIG} eingeplant und ist noch nicht zurück.
Vermutlich ist es im Alltag untergegangen, das passiert.

Bitte bringen Sie es in den nächsten Tagen zurück, damit es für die nächste
Person bereitsteht. Falls Sie es noch brauchen oder etwas dazwischenkam,
antworten Sie einfach auf diese Mail.

Viele Grüße
ZHL Medienausleihe
```

### Text (EN)
```
Hi {NAME},

the device {GERAET} was scheduled until {FAELLIG} and has not come back yet.
It probably just slipped through in everyday work, that happens.

Please return it in the next few days so it is ready for the next person. If you
still need it or something came up, simply reply to this email.

Best regards
ZHL Media Lending
```

---

## 3. Rückgabe-Erinnerung, Stufe 2 (deutlicher)

**An:** Ausleihende:r · **Wann:** ca. 3 Tage überfällig.

### Betreff
- **DE:** `Bitte zurückgeben: {GERAET} ist überfällig`
- **EN:** `Please return: {GERAET} is overdue`

### Text (DE)
```
Hallo {NAME},

das Gerät {GERAET} ist seit dem {FAELLIG} überfällig. Andere Lehrende und
Studierende warten häufig schon darauf.

Bitte geben Sie es zeitnah zurück. Wenn Sie es länger benötigen, melden Sie sich
kurz bei uns unter {KONTAKT}, dann finden wir gemeinsam eine Lösung.

Viele Grüße
ZHL Medienausleihe
```

### Text (EN)
```
Hi {NAME},

the device {GERAET} has been overdue since {FAELLIG}. Other teachers and
students are often already waiting for it.

Please return it soon. If you need it for longer, just let us know at {KONTAKT}
and we will find a solution together.

Best regards
ZHL Media Lending
```

---

## 4. Rückgabe-Erinnerung, Stufe 3 (letzte Erinnerung)

**An:** Ausleihende:r · **Wann:** ca. 7 Tage überfällig.

### Betreff
- **DE:** `Letzte Erinnerung: {GERAET} bitte zurückgeben`
- **EN:** `Final reminder: please return {GERAET}`

### Text (DE)
```
Hallo {NAME},

das Gerät {GERAET} ist seit dem {FAELLIG} überfällig, und wir haben Sie bereits
mehrfach erinnert. Dies ist unsere letzte Erinnerung.

Bitte geben Sie das Gerät jetzt zurück. Erfolgt keine Rückgabe, müssen wir Ihr
Ausleih-Konto vorübergehend sperren, bis das Material wieder da ist.

Wenn etwas im Weg steht, melden Sie sich bitte heute noch unter {KONTAKT}.

Viele Grüße
ZHL Medienausleihe
```

### Text (EN)
```
Hi {NAME},

the device {GERAET} has been overdue since {FAELLIG}, and we have reminded you
several times. This is our final reminder.

Please return the device now. If it is not returned, we will have to suspend your
lending account temporarily until the equipment is back.

If something is in the way, please reach us today at {KONTAKT}.

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

### Offene Mini-Entscheidungen für dich
1. Sollen die Mahnstufen-Schwellen bei 1 / 3 / 7 Tagen bleiben?
2. Anrede „Hallo {NAME}" oder lieber „Guten Tag {NAME}"?
3. Soll Stufe 3 die Konto-Sperre wirklich ankündigen, oder neutraler formulieren?
