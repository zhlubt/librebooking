# SPEC-UEBERGABE-MATRIX — Übergabe-/Einweisungs-Regeln pro Gerät (2026-06-26)

## Problem
Geräte ließen sich ohne Abholtermin ausleihen (z. B. Smartphone-Video-Kit). Ursache: der
Buchungspfad erzwang eine Abholung NUR bei `zhl_uebergabe.abholung = 'abholen_persoenlich'`;
46/48 Geräte standen auf dem Default `'abholen'` (nicht erzwungen) bzw. hatten gar keine Zeile.

## Entscheidungen (Nutzer)
- Matrix **pro einzelnem Gerät** (im Editor nach Geräte-Typ gruppiert).
- **Sichere Vorgabe:** unkonfigurierte Geräte = **Abholung Pflicht**.
- Kategorien der „Geräte einzeln"-Seite: fest im Code (separater Task).

## Datenmodell (bestehend, `zhl_uebergabe`, PK = resource_id)
- `abholung` varchar(24): **`abholen_persoenlich`** (Termin Pflicht) · **`ablageort`** (am Ablageort, kein Termin) · **`nicht_noetig`** (kein Termin). Legacy `abholen` zählt wie Pflicht.
- `einfuehrung` varchar(16): `keine` / `moeglich` / `notwendig`.
- `hauspost_allowed` tinyint: Hausdienst-Transport zusätzlich anbieten (nur bei Abholung wirksam).
- `abholort`, `rueckgabeort` varchar(200): Freitext-Orte.
- Erhalten bleiben: `einfuehrung_typ`, `tp_member_id`, `vorlauf_toleranz_h`, `booking_mode`.

## Enforcement
Neuer Helper `pickupApplies($ueb)` in **ZhlBookPresenter** UND **ZhlBundleBookPresenter**:
`return !in_array($ueb['abholung'], ['ablageort','nicht_noetig'], true);`
→ alles außer den zwei entspannten Modi (inkl. Default/Legacy `abholen`) braucht einen Abholtermin.
Ersetzt überall die alte `=== 'abholen_persoenlich'`-Prüfung (Pflicht/Anbieten/Auflösen).
Admin-Bypass (`!$user->IsAdmin`) bleibt unverändert. Slots sind verfügbar, weil
`fetchHandoverSlots` bei `tp_member_id = null` auf ALLE Mitglieder des Übergabe-Typs zurückfällt
→ kein Deadlock.

## Admin-Editor
`Web/zhl-uebergabe-admin.php` (SecurePage, Admin-Gate, CSRF). Eine große Form, alle aktiven
Geräte nach Typ gruppiert; je Gerät: Übergabe-Modus, Einweisung, Hausdienst (Checkbox),
Abhol-/Rückgabeort. POST = UPSERT (`ON DUPLICATE KEY UPDATE`) nur der editierten Spalten →
erhält die erweiterten Felder. Schreibt nur gegen `status_id=1`-Geräte gegengeprüfte IDs.
