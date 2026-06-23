# SPEC-UX-DASHBOARD — Neue Medienausleihe-Oberfläche

> **Status:** Entwurf v0.4 (2026-06-23) · §7/§10/§11 eingearbeitet · **v1 + v2a + v2b gebaut + auf media deployt** (Raster + Tags/Pools + Bundle-Katalog mit Live-Verfügbarkeit, read-only).
> **Grundlage:** Mock 3 „Dashboard" (`docs/zhl/mocks/3-dashboard.html`, live `https://media.zhl-ubt.de/Web/mocks/`).
> **Optik:** zhl-studio Style Guide (UBT-Grün `#009260`), siehe Memory `zhl-studio-design-system`.
> **Nordstern:** LibreBooking modernisieren — neue, einfachere UX (STRATEGY.md, Säule 3).

## 1. Leitprinzip — von „Katalog" zu „Vorhaben-Berater"
Lehrende & Studierende wissen oft **nicht**, wie das Gerät heißt („DJI MIC" sagt ihnen nichts),
**nicht**, was einfach/schwer zu bedienen ist, und **nicht**, was sie zusätzlich mitbuchen müssen.
Die Plattform erfasst deshalb das **Vorhaben** des Nutzers und schlägt daraus das beste, verfügbare
Material vor — statt ihn durch einen Geräte-Katalog zu schicken.

Fünf Prinzipien, die jede Anforderung unten prägen:
1. **Laien-Sprache vor Modellname.** Immer ein verständliches Tag zeigen („Funkmikrofon"), Modellname
   („DJI MIC") nur sekundär.
2. **Absicht vor Inventar.** Erst „was willst du machen?", dann Geräte — nicht umgekehrt.
3. **Bundles statt Einzelteile.** Der Nutzer bucht ein Vorhaben (z. B. „bewegtes Filmen"), das System
   stellt das Set zusammen (Kamera + Stativ + Gimbal + Ton) inkl. richtiger Stückzahl.
4. **Vorschlag statt Verbot.** Geht etwas nicht ganz, wird eine **Alternative** angeboten
   („nur 12 statt 14 Tage frei — reicht das?"), nie eine nackte „geht nicht"-Meldung.
5. **Ehrlich über Vorlauf.** Jedes Material hat eine konfigurierte Vorlaufzeit; kurzfristige Wünsche
   werden früh & freundlich abgefangen, nicht erst am Ende.

## 2. Nutzer & ihre Jobs (Jobs-to-be-done)
| Nutzer | Job | Schmerz heute |
|---|---|---|
| Lehrende:r (Laie) | „Ich will meine Vorlesung aufzeichnen" | kennt Gerätenamen nicht, weiß nicht was dazugehört |
| Studierende:r | „Ich will einen Imagefilm/Vlog drehen" | weiß nicht, was *bewegtes* Filmen braucht |
| Erfahrene:r Wieder-Bucher | „Schnell wieder das Set von letztem Mal" | will keinen Wizard, will direkt sehen was frei ist |
| ZHL-Team | Bundles/Tags/Vorlaufzeiten pflegen | heute nur über DB/Custom-Attribute |

→ Die UI muss **beide** Wege bedienen: geführt (Assistent, für Laien) **und** direkt
(Dashboard-Raster, für Wieder-Bucher). Das Dashboard-Layout vereint beides.

## 3. Screen-Regionen (Dashboard) & ihre Anforderungen
Aus Mock 3 abgeleitet:

- **A · Sidebar / Kategorien** — die 6 Kategorien (Audio, Video, Drohne, VR, Videostudio,
  Moderation) + Button **„✨ Assistent starten"**. Immer sichtbar = Überblick „das gibt es alles".
- **B · Suchleiste** — Freitext (mit **Tag-/Synonym-Suche**, nicht nur Modellname) + Zeitraum.
- **C · Ergebnis-/Verfügbarkeitsraster** — Geräte/Bundles als Karten mit **Tag als Titel**,
  Modellname klein, Verfügbarkeit pro Tag (frei/belegt/„nächster freier Tag").
- **D · Assistent (Dialog-Pfade)** — geführte Vorhaben-Abfrage, mündet in einen Bundle-Vorschlag.
- **E · Bundle-/Buchungs-Übergang** — Set prüfen, Alternativen wählen, Folge-Buchungen (Schnitt,
  Einweisung), dann in die echte LibreBooking-Reservierung.

## 4. Kern-Konzepte / Datenmodell
Pro Konzept: was es ist, und ob **nativ** (LibreBooking) oder **Custom** (neu zu bauen).

| Konzept | Beschreibung | Native vs. Custom |
|---|---|---|
| **Tag / Laien-Name** | Verständliches Label je Gerät („Funkmikrofon"), + Synonyme für die Suche | **Custom** (Tag-Tabelle o. Custom-Attribut); Suche heute nur SQL-LIKE (F25) |
| **Kategorie** | Die 6 Oberkategorien | Mapping auf vorhandene **Schedules/Resource-Groups** (nativ) |
| **Gerät = Einzel-Ressource** | jedes Gerät einzeln durchnummeriert (1 Gerät = 1 Nummer) | **Einzel-Ressourcen behalten** (Entscheidung §7.1) — keine Sammel-Ressource mit Kapazität |
| **Zähl-Typ** | gleiche Geräte zählbar machen: 7 Funkmikro-Sets; VR: **5 Typen × je 3**; 4 Schnittlaptops | **Custom Attribut/Tag „Geräte-Typ"** → Pool = Zahl freier Einzel-Ressourcen desselben Typs |
| **Menge / Pool** | Verfügbarkeit „N von M frei" im Zeitraum | abgeleitet aus dem Zähl-Typ (kein natives Kapazitätsmodell) |
| **Vorlaufzeit** | Min. Vorlauf je Material, **konfigurierbar** | **Nativ**: LibreBooking „Minimum Notice (Start)" je Ressource → Admin pflegt |
| **Zubehör** | Stativ, Gimbal, Objektive | **ebenfalls Einzel-Ressourcen** mit Zähl-Typ (Entscheidung §7.2) — nicht native Accessories |
| **Raum / Space** | Aufnahme-Raum (Seminarraum, Studio-Aufnahmetisch) | **neue buchbare Ressource(n)**; Studio heute buchbar, **Seminarraum noch nicht → anlegen** |
| **Bundle** | Benanntes Set aus Ressourcen/Typen + Stückzahl + Vorhaben | **Custom + Admin-Pflege-UI** (Admin legt Bundles an/ändert, §7.3) |
| **Dialog-Pfad** | Entscheidungsbaum Vorhaben→Bundle | **Custom, datengetrieben + Admin-pflegbar** (§7.4) |
| **Einweisung/Beratung** | Voraussetzung/Empfehlung je Gerät, 3 Stufen | **per-Gerät, Admin-definiert**; Nachweis = `ZhlCertificate` (F40): Admin ordnet zu, **unbegrenzt gültig, pro Gerät** (§7.7) — Stufen-Logik zu ergänzen |
| **Sequenz** | „erst Equipment, danach Schnittlaptop" | **zwei getrennte Buchungen desselben Users**, kein technischer Link (§7.5) |
| **Buchungs-Ziel** | Dashboard = neue Oberfläche/Trichter | legt **native LibreBooking-Reservierung** an; Multi-Ressource-Teilverfügbarkeit (US-13: „Res 1 nur 5, Res 2 volle 7 Tage") ist der **Custom-Mehrwert** — nativ nicht möglich (§7.6) |
| **Einweisungs-Slot** | Termin vor Ausgabe (Studio, Drohne, Beratung) | **vorhanden**: terminplaner/Übergabe-Modul (Slots, `handover`) — wiederverwenden |

## 5. Funktionale Anforderungen (User Stories + Akzeptanzkriterien)

### Überblick & Sprache
- **US-1 Überblick.** *Als Nutzer sehe ich auf einen Blick alle 6 Kategorien.*
  AK: Sidebar listet alle Kategorien mit Gerätezahl; Klick filtert Region C.
- **US-6 Laien-Tags.** *Als Laie erkenne ich Geräte an verständlichen Begriffen.*
  AK: Karten-Titel = Tag („Funkmikrofon"); Modellname („DJI MIC") klein darunter.
- **US-7 Tag-Suche.** *Als Nutzer finde ich „Funkmikrofon", auch wenn das Gerät „DJI MIC" heißt.*
  AK: Suche trifft über Tags/Synonyme, nicht nur Modellname.

### Verfügbarkeit-first (kein Klick ins Leere)
- **US-2 Nur erlaubte/freie Tage.** AK: gesperrte Tage nicht wählbar; belegte Tage **vor** dem Klick
  als belegt markiert; Rechte (Permissions/Einweisung F40) berücksichtigt.
- **US-3 Belegung sichtbar.** AK: pro Gerät Tages-Status frei/belegt + „nächster freier Tag".
- **US-5 Klarer Fehler statt Sammel-Fehler.** *Wenn etwas nicht buchbar ist, sehe ich sofort warum.*
  AK: pro Schritt/Position eine eindeutige Meldung; nie ein undurchsichtiger Fehler am Ende.

### Vorhaben-Assistent & Bundles
- **US-4 Geführter Vorschlag.** *Ich sage „Audio, 15.–21.7." → passende, freie Geräte.*
  AK: Was→Wann→Vorschläge; Ergebnis respektiert Rechte + Verfügbarkeit + Vorlauf.
- **US-8 Vorhaben statt Gerät.** *Ich beschreibe, was ich vorhabe, nicht welches Gerät ich brauche.*
  AK: Assistent fragt nach dem Ziel (z. B. „statische Vorlesungsaufzeichnung" vs. „bewegtes Filmen")
  und leitet daraus das Material ab.
- **US-9 Bundle als Einheit.** *Ich buche ein Set, nicht 5 Einzelteile.*
  AK: Bundle zeigt alle Bestandteile + Stückzahl; eine Aktion bucht alle; fehlende Teile sind markiert.
- **US-10 Mitbuch-Hinweise.** *Mir wird gesagt, was ich zusätzlich brauche.*
  AK: Bundle ergänzt nötiges Zubehör (Stativ, Karte, Akku) automatisch; Optionales ist abwählbar.
- **US-11 Schwierigkeitsgrad.** *Ich sehe, ob etwas einfach oder schwer zu bedienen ist.*
  AK: jedes Bundle/Gerät trägt eine Einstufung „einfach/fortgeschritten"; der Assistent bevorzugt für
  Laien einfache Optionen, bietet schwere bewusst an.

### Mengen, Alternativen, Verhandlung
- **US-12 Mengen-Bundle.** *Ich brauche 5 VR-Brillen.*
  AK: System prüft den Pool (z. B. 7 vorhanden) und bestätigt 5 für den Zeitraum **oder** schlägt
  Alternativen vor.
- **US-13 Alternative statt „geht nicht".** *Wenn mein Wunsch nicht ganz passt, bekomme ich einen
  Gegenvorschlag.* AK: bei Teil-Verfügbarkeit (z. B. 12 von 14 Tagen) Vorschlag „geht 12 Tage — reicht
  das, oder kürzer/anderer Start?"; nie nur eine Ablehnung.
- **US-14 Pool-Bewusstsein.** AK: Stückzahlen (7 Funkmikrofon-Sets, 4 Schnittlaptops) werden bei jedem
  Bundle-Vorschlag berücksichtigt; Überbuchung ausgeschlossen.

### Sequenzen & Folge-Schritte
- **US-15 Schnitt-Folgebuchung.** *Nach dem Drehen will ich schneiden.*
  AK: Wenn das Vorhaben Videoaufnahme enthält, fragt das System nach dem Schnitt und bietet einen
  **Schnittlaptop** an — als **Folge-Buchung im Anschluss** (eigener Zeitraum nach der Drehphase).
  Es entstehen **zwei getrennte Buchungen desselben Nutzers** (keine technische Verknüpfung — bewusst
  einfach, §7.5); der Schnittlaptop ist **optional**, nie Pflicht.
- **US-16 Einweisung vorab (zwei Wege).** *Vor dem Videostudio ist eine Einführung zwingend.*
  AK: Bundle „Videostudio" lässt eine Buchung nur zu, wenn der Nutzer eine gültige Studio-Einweisung
  hat **oder** sich jetzt für eine entscheidet. Zwei Wege:
  **(A) Monatsseminar** „Studio-Einführung" (1× pro Monat) — Anmeldung; nach Teilnahme gilt die
  Einweisung dauerhaft.
  **(B) Einzeltermin** mit einem ZHL-Menschen vor Ort — Slot aus der Kategorie **„Geräteeinweisungen"**
  im terminplaner-/Übergabe-Portal (vorhandene Slot-Mechanik).
  Eine absolvierte Einweisung wird **erfasst** und gilt als Voraussetzung (Anknüpfung an das
  F40-Zertifikats-/Einweisungs-Modell `ZhlCertificate`).
- **US-19 Studio-Aufnahmeset wählen.** *Ich stelle mein Studio-Setup über einfache Fragen zusammen.*
  AK: Der Studio-Pfad fragt nacheinander:
  **(1) Personenzahl** alleine / 2 / 3 / 4 → bestimmt die Zahl der **Funkmikrofone** (max. 4 verfügbar;
  größere Wünsche → Hinweis statt Fehler, US-13).
  **(2) Mit Folien im Hintergrund?** *Ja* → Hinweis-Text „baue deine Folien so, dass **rechts unten
  Platz** bleibt, damit du dort stehen kannst" **und** ein **Laptop mit USB-C** wird als nötiges Teil
  ergänzt (für die Bild-Verbindung). *Nein* → reine Aufnahme, **kein Laptop** nötig.
  Das Ergebnis ist ein konkretes Studio-Bundle (Slot + N Mikros + ggf. Laptop) plus die Pflicht-
  Einweisung aus US-16.

### Vorlaufzeiten
- **US-17 Vorlauf ehrlich.** *Kurzfristige Wünsche werden früh abgefangen.*
  AK: jedes Material kennt eine konfigurierte Vorlaufzeit; wählt der Nutzer einen zu nahen Start, sagt
  das System sofort „dieses Material braucht X Tage Vorlauf — frühester Start: …".
- **US-18 Vorlauf pflegbar.** *Das ZHL-Team kann Vorlaufzeiten anpassen.*
  AK: Vorlauf je Ressource im Admin änderbar (native Min-Notice), ohne Code-Änderung.

### Räume & Einweisungs-Stufen
- **US-20 Einweisung in 3 Stufen.** *Je Gerät kann eine Einweisung unterschiedlich verbindlich sein.*
  AK: Das ZHL-Team legt **pro Gerät** (Admin-pflegbar) eine Stufe fest:
  **① zwingend** — blockt die Buchung bis Nachweis (z. B. Videostudio, Drohne);
  **② empfehlenswert** — nur Hinweis/Angebot, blockt **nicht** (z. B. Podcast);
  **③ Experten-Beratung „für danach"** — Gespräch über Weiterverarbeitung/Workflow (z. B. Insta360),
  wird angeboten (optional oder zwingend einstellbar).
  Nachweis je Stufe ①/③ = `ZhlCertificate` (Admin ordnet zu, **unbegrenzt gültig, pro Gerät**).
- **US-21 Aufnahmeort erfragen (Raum optional anbieten).** *Bei Aufnahme-Vorhaben fragt das System,
  wo aufgenommen wird.*
  AK: Der Dialog stellt die Frage **„Wo willst du aufnehmen?"** (z. B. eigener Raum / Seminarraum /
  Videostudio-Aufnahmetisch). Einen **Raum mitzubuchen ist optional** — das System *kann* einen Raum
  anbieten (Seminarraum oder Studio), erzwingt ihn aber nicht. Räume sind eigene buchbare Ressourcen;
  **Seminarraum ist heute noch nicht buchbar → bei Bedarf anzulegen**. Mehrere mögliche Räume =
  Alternativen (US-13).

## 6. Beispiel-Dialogpfade (zu konkretisieren mit dem ZHL-Team)
Die Bundles aus der Anforderung, als Entscheidungsbäume skizziert:

**a) Vorlesung/Vortrag aufnehmen**
„Was willst du aufnehmen?" → *Statische Vorlesungsaufzeichnung* → Bundle: feste Kamera + Stativ +
Ansteck-/Funkmikrofon (einfach). *Interaktives/bewegtes Filmen* → Bundle: Kamera + **Gimbal** +
Funkmikro-Set + ggf. 2. Person (fortgeschritten).

**b) Imagefilm/Vlog (bewegt)**
→ Bundle „Sony-Cam + 3 Mikros + Stativ + Gimbal" **oder** „BM Pocket 6K + 3 Objektive + Stativ +
Gimbal" je nach Anspruch (einfach vs. profi) → danach **US-15 Schnitt?** → Schnittlaptop im Anschluss.

**c) VR-Lehrveranstaltung**
„Wie viele Teilnehmende gleichzeitig?" → N → Mengen-Bundle „N VR-Brillen" (Pool 7) → US-12/US-13.

**d) Videostudio** (Detail-Pfad, US-19 + US-16)
1. „Mit wie vielen Personen nimmst du auf?" → *alleine / 2 / 3 / 4* → Funkmikro-Zahl (max. 4).
2. „Mit Folien im Hintergrund?"
   - *Ja* → Hinweis „Folien so bauen, dass **rechts unten Platz** zum Stehen bleibt" + **Laptop mit
     USB-C** wird Teil des Bundles (Bildverbindung).
   - *Nein* → reine Aufnahme, **kein Laptop**.
3. **Pflicht-Einweisung** (US-16): hat der Nutzer schon eine gültige Studio-Einweisung? Wenn nein →
   Wahl **(A) Monatsseminar** oder **(B) Einzeltermin „Geräteeinweisungen"** (terminplaner-Slot) — erst
   danach Studio-Slot buchbar.
4. Verfügbarkeits- & Vorlauf-Prüfung des Studio-Slots → Buchung (mit Alternativen, US-13).

**e) Vlog leichtgewichtig**
→ Bundle „Vlog-Halterung + Mikrofon" (einfach, kein Schnittzwang).

**f) Podcast (Audio)**
→ Bundle „Podcast" = Mikrofone (Zahl nach Personen). Frage **„Wo willst du aufnehmen?"** (US-21) → das
System *kann* einen Raum anbieten (Seminarraum *oder* Studio-Aufnahmetisch), Pflicht ist es nicht.
Einweisung **empfehlenswert, nicht zwingend** (US-20 ②). Schnitt: Schnittlaptop **optional** (US-15).

**g) Drohne**
→ Bundle „Drohne" → **zwingende Einweisung** (US-20 ①) vor Ausgabe → Verfügbarkeit/Vorlauf → Buchung.

**h) Insta360-Kamera**
→ Gerät „Insta360" → **Experten-Gespräch „für danach"** (US-20 ③, Beratung zur Weiterverarbeitung)
wird angeboten → Verfügbarkeit/Vorlauf → Buchung.

> Jeder Pfad endet in einem **konkreten Bundle-Vorschlag** mit Verfügbarkeits- & Vorlauf-Prüfung,
> Alternativen (US-13) und optionalen Folge-Buchungen (US-15/US-16).

## 7. Entscheidungen (vom ZHL-Team beantwortet 2026-06-23 — in §4/§5/§6 eingearbeitet)
1. **Mengen-Modell:** 7 Funkmikro-Sets / 4 Schnittlaptops als **N Einzel-Ressourcen** (genaue
   Geräte-Zuordnung, aber mehr Pflege) **oder** als **1 Ressource mit Kapazität N** (einfacher, aber
   LibreBooking-Kapazitätssemantik prüfen)?
   --> nein, wir haben die geräte bereits mit 1-7 durchnummeriert. bei uns hat jedes gerät eine einzige Nummer. das sollten wir so lassen. bei librebooking müssten wir mit kategorie/tag/attribut xy arbeiten, sodass gleiche geräte gezählt werden können. gleiches gilt für VR-Brillen: wir haben 5 Typen jeweils 3 Mal.
2. **Zubehör-Modell:** Stativ/Gimbal/Objektiv als native **Accessories** (mengengeführt, an Ressource
   gehängt) **oder** als eigene buchbare Ressourcen (eigene Verfügbarkeit)?
   --> siehe antwort bei 1. 
3. **Bundle-Definition:** Wo gepflegt — eigene Tabelle/Config-UI fürs ZHL-Team vs. statisch im Code v1?
--> das sollte ein admin pflegen können, denn heute kann ich noch nicht alle möglichen und releveanten bundles nachvollziehen. 
4. **Dialog-Pfade:** datengetrieben (konfigurierbarer Baum) vs. fest verdrahtet für die ersten 4–5
   Vorhaben (schneller zu v1)?
   -> ja, sehr gut. aber auch wenn möglich pflegbar für den admin. 
5. **Sequenz-Buchung:** zwei verknüpfte Reservierungen mit Hinweis vs. ein gemeinsamer Vorgang?
--> nein, das sind vom gleichen user zwei verschiedene buchungen (damit es nicht zu kompliziert wird)
6. **Verhältnis zur nativen LibreBooking-Buchung:** ersetzt das Dashboard die Reservierungs-Matrix
   ganz, oder ist es ein vorgelagerter „Vorhaben-Trichter", der am Ende eine native Reservierung anlegt?
   (Empfehlung: Trichter davor, native Buchung als Ziel — weniger Risiko.)
   -> ich möchte eigentlich nur die oberfläche verändern, da librebooking überkomplex ist. angelegt werden könnte eine native reservierung. allerdings wird sowas wie: "ressource 1 ist nur 5 tage und ressource 2 ist wie gewünscht 7 tage" nicht nativ funktionieren. 
7. **Einweisungs-Nachweis:** Wie wird eine absolvierte Einweisung (Seminar **oder** Einzeltermin)
   erfasst und als Voraussetzung geprüft? Anknüpfung an `ZhlCertificate` (F40) — wer trägt die
   Seminar-Teilnahme ein, wie lange gilt sie, gilt sie pro Gerät/Studio oder pauschal?
   -> die geltungsdauer ist unbegrenzt. ein admin muss dem user dieses zertifikat zuordnen. Das gilt pro Gerät einzeln. Die Geräte, die Einweisung brauchen, will ich manuell als admin definieren und pflegen können. 

## 8. Phasen / Out-of-Scope v1
Vorschlag zur Schneidung (Details nach Codex):
- **v1 (Fundament):** Dashboard-Layout (A–C) + Tags/Laien-Namen + verfügbarkeit-first Raster +
  Vorlauf-Hinweise. Noch keine Bundles/Dialoge.
  Enabling-Infra: **Zähl-Typ-Attribut** auf Ressourcen + **Seminarraum als Ressource anlegen**.
- **v2 (Bundles):** Bundle-Definitionen (+ Admin-Pflege-UI) + Mengen/Pool + Räume (US-21) +
  Alternativen-Vorschlag (US-9..US-14, US-21).
- **v3 (Assistent/Dialoge):** Vorhaben-Dialogpfade (US-8/US-11) + Einweisungs-Stufen (US-20) +
  Sequenz-Folgebuchung (US-15) + Einweisung/Beratung-Termine (US-16).
- **Out-of-Scope v1:** Empfehlungs-„KI", Bewertungen, Foto-Vorschau je Gerät.

## 9. Datenquellen-Mapping (Kurz)
- Kategorien/Geräte/Verfügbarkeit: `schedules`, `resources`, `reservation_series`/`reservation_instances`,
  Blackouts — **nativ** lesbar (read-only Endpunkt fürs Dashboard).
- Rechte/Einweisung (3 Stufen, per-Gerät, Admin-zugeordnet, unbegrenzt gültig): `*_resource_permissions`
  + F40-Zertifikat (`ZhlCertificate`) — **vorhanden**; Stufen-Logik zwingend/empfehlenswert/Beratung **neu**.
- Vorlauf: native Min-Notice je Ressource — **vorhanden/konfigurierbar**.
- Zähl-Typ/Pool (gleiche Geräte zählen): Custom-Attribut auf den Einzel-Ressourcen — **neu**.
- Räume: eigene buchbare Ressourcen — Studio **vorhanden**, **Seminarraum neu anzulegen**.
- Tags/Bundles/Dialoge: **neu** (Custom-Tabellen + Pflege-UI fürs ZHL-Team).
- Einweisungs-Slots: terminplaner + `zhl_booking_handover` — **vorhanden** (Übergabe-Modul).

## 10. Technische Umsetzung — nativ vs. custom (am 5.1.0-Code verifiziert 2026-06-23)
Vier Recherche-Agenten + ein Codex-Gate (§11) haben die Spec-Annahmen gegen den echten Code geprüft.
Ergebnis: **für Lesen/Schreiben gibt es native Bausteine** — aber die Dashboard-Orchestrierung, die
zusammenhängenden Alternativ-Fenster, Pool-Zuweisung, Bundles, Dialoge und Einweisungs-Stufen sind
**eigenständige Custom-Domänenlogik** (nicht „nur dünn"). Die native **Save-Validierung bleibt stets
letzte Instanz**; das Dashboard zeigt eine **Prognose**.

### 10.1 Oberfläche / Seite
- Neue Seite nach **MVP-Muster (SecurePage)**: `Web/zhl-dashboard.php` + `Pages/ZhlDashboardPage.php` +
  `Presenters/ZhlDashboardPresenter.php` + `tpl/zhl-dashboard.tpl` (Vorbild `Web/dashboard.php`,
  `Pages/DashboardPage.php`). pageDepth 0; `globalheader.tpl` inkludieren → Theme (`zhl-theme.css` via
  `css.extension.file`) + Menü automatisch. **Nicht** `zhl-welcome.php` als Vorlage (bewusst kein MVP).
- **Als Startseite (gestuft, Codex):** **zuerst** separate SecurePage + Menü-Link (kein Core-Edit, sofort
  testbar). **Dann** fürs Login-Landing ein **isolierter, `// ZHL:`-markierter Core-Patch** (`Pages.php`
  ID-Map + `ConfigKeys::DEFAULT_HOMEPAGE`-choices, `default.homepage`) — als Upgrade-Patch dokumentiert,
  **ohne** das native Dashboard zu überschreiben. Native Seiten bleiben erreichbar.
- Eigenes CSS/JS: `Web/css/zhl-dashboard.css` + `Web/scripts/zhl-dashboard.js` via `{cssfile}/{jsfile}`.

### 10.2 Lesen (verfügbarkeit-first Raster) — NATIV
- Belegung je Ressource/Zeitraum: `ResourceAvailability::GetItemsBetween()` /
  `ReservationService::Search()` (mergen Reservierungen **+ Blackouts**) — das ist eine **Belegungsliste,
  kein Buchbarkeits-Gate**. `ReservationConflictResult::AllowReservation()` prüft nur
  Konflikte/Blackouts/MaxConcurrent, **nicht** Rechte/Vorlauf/Quoten/Schedule (Codex). „Wirklich
  verfügbar" = ein **eigener ZHL-Verfügbarkeits-Service** (§11.1), der Rechte + F40 + Status +
  Schedule-Slots + Buffer + Vorlauf + Quoten + MaxConcurrent zusammenführt; Anzeige = Prognose, der
  native Save bleibt maßgeblich.
- Multi-Ressource availability-first inkl. freier Fenster: `SearchAvailabilityPresenter::SearchAvailability()`
  + `PotentialSlot` → `AvailableOpeningView[]`.
- Filter: Kategorien = `resource_groups`; Zähl-Typ/Tags = `ScheduleResourceFilter`/`AttributeFilter`.
- ZHL-Endpunkt: **schlanker read-only JSON-Endpunkt** ruft diese Bausteine — kein roher SQL-Neubau.

### 10.3 Schreiben (Buchung anlegen) — NATIV
- Native REST `POST .../Reservations/` (`ReservationWriteWebService::Create`, DTO `ReservationRequest`:
  `resourceId`+`startDateTime`+`endDateTime` Pflicht, `resources[]` für Multi-Gerät) **oder** intern
  `ReservationSavePresenter` → `ReservationHandler::Handle()`. Beide laufen durch das native
  Validierungs-/Konflikt-Gate (Verfügbarkeit **+ Vorlauf** automatisch) → kein Doppelbuchungs-Risiko.

### 10.4 Der eigentliche Custom-Mehrwert (dünne Schicht)
- **Teilverfügbarkeit + Alternative (US-13):** **neue eigene Intervall-Logik** — *nicht* durch Anpassen
  von `AllDaysAreOpen()` (liefert nur Boolean, ist auf Wiederhol-Termine gemünzt, Start-Vergleich sogar
  auskommentiert — Codex). Eine Reservierung ist ein **durchgehender** Zeitraum; einzelne freie Tage sind
  keine gültige Alternative. ZHL berechnet aus der Roh-Belegung (`ResourceAvailability::GetItemsBetween`)
  **zusammenhängende freie Fenster/Präfixe** und schlägt konkrete alternative **Start/Ende** vor
  („14 Tage gehen nicht — aber durchgehend Mo–Fr (5 Tage), oder Start 2 Tage später").
- **Pool-Zählung (US-12/14):** `COUNT` der freien Einzel-Ressourcen mit gleichem Zähl-Typ im Zeitraum.
- **Vorhaben-Dialoge + Bundles (US-8/9/20/21):** eigene Logik + Pflege-UI (s. 10.5/10.6).

### 10.5 Daten — was nativ, was neu
- **Nativ, keine neuen Tabellen:** Kategorie → `resource_groups` (hierarchisch, N:M); Zähl-Typ →
  Custom-Attribut (Kategorie RESOURCE, Typ SELECT_LIST, **`is_required`** für saubere Zählung — aber
  **interne Attribut-/Ressourcen-ID referenzieren, nie den umbenennbaren Anzeigetext**, Codex; SELECT_LIST
  hat keine FK-Integrität); Laien-Tags → Text-Custom-Attribut (LIKE-Suche); Vorlauf →
  `resources.min_notice_time_add` (Admin-UI „Manage Resources"). **Pool stets aus konkreten buchbaren
  Ressourcen-IDs** bilden (Rechte/Status/Schedule/Blackouts/Buffer je ID).
- **Neu (Custom-Tabellen + Admin-Pflege-UI, §7.3/7.4):** Bundles (`zhl_bundle`, `zhl_bundle_item` mit
  Zähl-Typ + Menge + optional/Pflicht), Vorhaben-Dialogpfade (datengetrieben, `zhl_dialog_*`),
  Einweisungs-Stufen je Gerät — **neues eigenes Stufenmodell** (NICHT bloße F40-Erweiterung: `ZhlCertificate`
  ist selbst Custom auf der nativen Permission-Schnittstelle und kennt nur „Zertifikat erforderlich", keine
  Stufen/Beratung/Termin — Codex). Eigene Semantik: ① zwingend → hartes Gate; ② empfehlenswert → **nie ins
  Permission-Gate**, nur Hinweis; ③ Experten-Beratung „für danach" (Termin, optional/zwingend). Mit
  FK/Widerruf/Aussteller/Audit; Zuordnung Admin, unbegrenzt gültig.
- **Räume:** neue native Ressourcen (Seminarraum anlegen).

### 10.6 Gotchas (aus der Code-Recherche — unbedingt beachten)
- Vorlauf-Regeln sind für **Admins ausgenommen** (`AdminExcludedRule`); Vorlauf = **Echtzeit-Minuten,
  keine Werktage**.
- `MaxConcurrentReservations` **nicht** für Zähl-Typ nutzen (zerstört Einzel-Identität); beim
  „belegt?"-Anzeigen aber beachten (>1 parallele Buchung möglich).
- Verfügbarkeits-Reads in **UTC**; **Buffer-Zeiten + Blackouts** mitprüfen (sonst Anzeige ≠ nativer Save).
- SELECT_LIST-Attribut ist nur bei `is_required` wertvalidiert → sonst verfälschen Tippfehler die `COUNT`.
- `SearchAvailability` lädt **max. 100 Ressourcen** → Filter/Paginierung einplanen.
- Beim Deploy `config.php` **nie aus dist** überschreiben (`css.extension.file`); nach `.tpl`-Änderung
  `tpl_c/` leeren; Page-IDs in `Pages.php` hartcodiert; bei Config-Key-Änderung `composer config-dist:generate`.

## 11. Codex-Gate (2026-06-23) — Befunde eingearbeitet
Voller Bericht: `docs/zhl/codex-findings.md`. Codex bestätigte die nativen Lese-/Schreib-Bausteine,
korrigierte aber drei zu optimistische Stellen (oben bereits eingearbeitet):
- **US-13 ≠ `AllDaysAreOpen`** → eigene **zusammenhängende-Fenster**-Logik (einzelne freie Tage sind keine
  gültige Alternative; Multi-Ressource-Reservierungen erzwingen denselben Zeitraum).
- **„buchbar" ≠ `AllowReservation()`** → Dashboard = Prognose; vollständige **Save-Validierung** bleibt
  letzte Instanz (Race Anzeige↔Save einplanen).
- **F40/Einweisung = Custom**, 3-Stufen-Modell neu; Stufe ② nie ins harte Gate.

**Vor dem Bau zu klären / zu ergänzen (Codex „Fehlt"):**
1. **§11.1 — Verbindliche Definition „verfügbar"**: ein einziger **ZHL-Verfügbarkeits-Service** als
   Wahrheitsquelle fürs Raster (Rechte + F40 + Status + Schedule-Slots + Buffer + Vorlauf + Quoten +
   MaxConcurrent), gespeist aus den nativen Bausteinen.
2. **Stabile Referenzen**: Bundle-Items/Dialogknoten/Pool an interne IDs binden, nicht an SELECT_LIST-Texte.
3. **Pool-Auswahlregeln**: bevorzugte Gerätenummern, defekte/deaktivierte Geräte, mehrere Schedules.
4. **Bundle-Versionierung**: Historie reproduzierbar; Änderungen brechen Altbuchungen nicht.
5. **Sequenz-Buchungen** (zwei native Reservierungen) sind **nicht atomar** → Rollback-/Hinweis-Konzept.
6. **F40-Schema härten**: FKs auf users/resources, Widerruf, Aussteller, Begründung, Audit.
7. **Einweisung pro physischem Gerät vs. pro Gerätetyp** fachlich entscheiden.
8. **Core-Patches** (Homepage-ID, Config-Choices, Plugin-Whitelist) klein, `// ZHL:`-markiert,
   automatisiert wiederanwendbar (Upgrade-Sicherheit).

---
**Stand v1 (gebaut + deployt):** `Web/zhl-dashboard.php` (separate SecurePage, read-only) +
`Pages/ZhlDashboardPage.php` + `Presenters/ZhlDashboardPresenter.php` + `lib/Application/Zhl/ZhlAvailabilityService.php`
(die §11.1-Wahrheitsquelle) + `tpl/zhl-dashboard.tpl` + `Web/css/zhl-dashboard.css` (zhl-studio-Optik).
Kategorien = Schedules; verfügbarkeit-first Raster (frei/belegt je Tag, rechte-/status-gefiltert);
„Buchen" übergibt an die native `reservation.php`. Smoke gegen echte media-Daten: 58 Geräte, 5 Kategorien,
Belegung/Tag + Such-/Kategorie-Filter ok; Seite lädt sauber (302 unauth). **Bewusst noch nicht:**
Laien-Tags (brauchen das Zähl-Typ/Tag-Attribut, v2), Bundles/Dialoge (v2/v3), Homepage-Core-Patch.

**Stand v2a (gebaut + deployt):** „Geräte-Typ" als Ressourcen-Custom-Attribut (Migration `006`,
SINGLE_LINE, name-heuristischer Seed → 56/58 Geräte getaggt) dient gleichzeitig als **Laien-Tag**
(Titel „Funkmikrofon", Modellname als Untertitel — US-6), als **Such-Schlüssel** (US-7) und als
**Pool-Schlüssel**: das Dashboard zeigt je Typ „N von M frei" (US-12/US-14). Pflege/Verfeinerung der
Typen nativ über „Manage Resources". (Codex-Hinweis: für Bundles später stabile Typ-Referenz statt
Anzeigetext.) Smoke gegen echte media-Daten: 15 Pools korrekt, Typ-Suche ok.

**Stand v2b (gebaut + deployt):** Bundle-Katalog (`zhl_bundle`/`zhl_bundle_item`, Migration `007`) mit
9 Start-Bundles aus den §6-Beispielen (Vlog/Vorlesung/Imagefilm/Profi-Film/VR/Podcast/Studio/Drohne/
Insta360). Dashboard-Bereich „Vorhaben & Bundles" zeigt je Bundle die Komponenten (Typ×Menge,
Pflicht/optional, Hinweis, Schwierigkeit) + **Live-Verfügbarkeit je Position** (aus dem Pool je Typ);
`ZhlBundleService`: „verfügbar = alle Pflicht-Positionen erfüllbar". Buchung weiter über die konkreten
Einzelgeräte (Trichter). Verifiziert: 9 Bundles, Mengen-Schwellen korrekt.

**Nächste Schritte:** (1) eingeloggt ansehen (`https://media.zhl-ubt.de/Web/zhl-dashboard.php`);
(2) **v2c: Admin-Pflege-UI** für Bundles (CRUD) + stabile Typ-Referenz statt Freitext (Codex);
(3) v3: Vorhaben-Dialoge (Assistent) + Einweisungs-Stufen + Sequenz/Räume.
