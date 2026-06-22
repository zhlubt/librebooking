# Agentischer Prozess: Feature-Listen prüfen & weiterentwickeln

> Ziel: Die Feature-Liste ([FEATURES.md](FEATURES.md)) wird **systematisch, agentisch
> und überprüfbar** gepflegt — jede Einstufung („nativ / Konfig / Custom") wird gegen
> den echten LibreBooking-Code + Live-Kopie belegt, und **jeder Plan wird von Codex
> gegengecheckt**, bevor er als gültig gilt.

## Quellen der Wahrheit
- **[FEATURES.md](FEATURES.md)** — strukturierte Liste (eine Zeile pro Feature, mit
  Status, Beleg, Config-Key, nächstem Schritt, „verifiziert durch").
- **[PROGRESS.md](PROGRESS.md)** — roter Faden (Stand, nächste Schritte, Entscheidungen).
- Code: dieser Fork (`zhl-main`), Live-Kopie `../zhl-buchungssystem-LIVE-COPY/`,
  laufende lokale Instanz (5.1.0 + Live-DB-Kopie).

## Rollen (Agenten)
Jede Rolle ist ein eng umrissener Auftrag mit definiertem Output (passt für Subagenten):

1. **Scout** — nimmt ein Feature und sucht im LibreBooking-Code/Doku/Live-Kopie nach
   dem belegbaren Ist-Zustand. Output: Beleg (Datei/Pfad/Config-Key) + Einstufung
   `native | config | partial | custom` + Begründung.
2. **Klassifizierer** — verdichtet die Scout-Funde, setzt den Status in FEATURES.md,
   benennt den konkreten nächsten Schritt (z.B. „Config-Key X setzen", „Plugin Y bauen").
3. **Adversarial-Checker** — versucht die Einstufung zu **widerlegen** („ist das wirklich
   nativ? Edge-Cases? deutsche UX?"). Verhindert zu optimistische „ist schon da"-Urteile.
4. **Synthese** — schreibt/aktualisiert FEATURES.md + PROGRESS.md, leitet PR-würdige
   Arbeitspakete ab (je 1 Branch/PR gegen `zhl-main`).
5. **Codex-Gate** — siehe unten: Pflicht-Gegencheck vor Übernahme.

> Umsetzung: Diese Rollen können als einzelne Subagenten laufen (Scout/Checker
> parallel pro Feature), oder — bei Bedarf — als Workflow orchestriert. Klein anfangen:
> erst die `partial/custom`-Kandidaten (F8, F10, F16/F17, F25, F30) tief prüfen.

## Pflicht-Gate: Codex-Gegencheck
**Kein Plan / keine Feature-Einstufung gilt als final, bevor Codex ihn gegengecheckt hat.**

- Bei jedem nennenswerten Plan/Update läuft ein Codex-Review (Skript:
  [`codex-review.sh`](codex-review.sh)).
- Codex prüft konkret: Sind die „nativ"-Behauptungen real (im Code belegbar)? Fehlt
  etwas? Sind Risiken/Reihenfolge plausibel? Wird Upstream-Core unnötig angefasst?
- Codex-Funde werden in den Plan eingearbeitet **oder** mit Begründung verworfen
  (dokumentiert im PR / PROGRESS-Changelog).
- Prinzip aus der Projekt-Erfahrung: **am Live-Artefakt verifizieren**, nicht nur am Text.

## Arbeitszyklus (eine Iteration)
1. Feature(s) aus FEATURES.md mit Status „⏳/unklar" wählen (zuerst `partial/custom`).
2. **Scout + Adversarial-Checker** (gern parallel) sammeln Belege an Code/Live-Kopie.
3. **Klassifizierer/Synthese** aktualisieren FEATURES.md + PROGRESS.md.
4. **Codex-Gate**: `./docs/zhl/codex-review.sh` → Funde einarbeiten.
5. Daraus PR-Arbeitspakete ableiten (Branch+PR gegen `zhl-main`), umsetzen, lokal
   gegen die Live-DB-Kopie verifizieren.
6. PROGRESS-Changelog fortschreiben → roter Faden bleibt erhalten.

## Definition of Done je Feature
- Status in FEATURES.md belegt (Pfad/Config-Key) **und** durch Codex-Gate bestätigt.
- Nächster Schritt ist ein konkretes, umsetzbares Arbeitspaket (oder „erledigt").
- Bei Umsetzung: lokal an der Live-DB-Kopie verifiziert, PR gegen `zhl-main`.
