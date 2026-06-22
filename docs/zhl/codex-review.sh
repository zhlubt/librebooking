#!/usr/bin/env bash
# Codex-Gate: lässt Codex die ZHL-Pläne gegen den echten LibreBooking-Code prüfen.
# Read-only (Sandbox), nicht-interaktiv. Findings landen in docs/zhl/codex-findings.md.
#
# Usage:
#   ./docs/zhl/codex-review.sh                # prüft die Standard-ZHL-Pläne
#   ./docs/zhl/codex-review.sh "Freitext..."  # eigener Review-Fokus
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="$REPO_ROOT/docs/zhl/codex-findings.md"
FOCUS="${1:-}"

PROMPT="Du bist ein kritischer Gegenleser (Codex-Gate) für ein ZHL-Projekt, das auf
LibreBooking (dieser Repo-Fork, Branch zhl-main, Basis v5.1.0) aufsetzt.

Prüfe die ZHL-Pläne in docs/zhl/ — vor allem FEATURES.md und STRATEGY.md — GEGEN den
tatsächlichen LibreBooking-Code in diesem Repo (Pages/, Presenters/, Domain/, lib/,
WebServices/, lang/, config/config.dist.php, database_schema/).

Konkret:
1) Jede mit Status ✅ 'nativ' markierte Feature-Zeile: Stimmt das? Nenne die belegende
   Datei/Klasse/Config-Key ODER widerlege die Behauptung.
2) Falsche oder zu optimistische Einstufungen (z.B. etwas als 'nativ', das real Custom
   braucht) klar benennen.
3) Fehlt etwas Wichtiges in der Liste/Strategie? Risiken im Upgrade-/Vorgehen?
4) Wird unnötig Upstream-Core angefasst, wo Config/Plugin reichen würde?

Sei knapp und konkret. Gib NUR Markdown aus: eine Tabelle 'Feature/Plan | Befund |
Beleg (Pfad) | Empfehlung' plus kurze Abschnitte 'Fehlt' und 'Risiken'. Ändere KEINE
Dateien.${FOCUS:+

Zusätzlicher Fokus: $FOCUS}"

echo "Running Codex review (read-only)... -> $OUT"
codex exec -s read-only -C "$REPO_ROOT" --skip-git-repo-check -o "$OUT" "$PROMPT"
echo "Done. Findings: $OUT"
