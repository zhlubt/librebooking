# Dev-Workflow (ZHL-Fork)

Ziel: Kollegen können auf GitHub jederzeit nachvollziehen, was passiert — über
häufige, saubere Commits und Pull Requests.

## Branch-Modell
- **`zhl-main`** ist unser stabiler Basis-Branch (geschützt, nur via PR). Basis: Upstream-Tag `v5.1.0`.
- Pro Aufgabe ein eigener Branch, dann PR **gegen `zhl-main`**.
- `upstream` = `LibreBooking/librebooking` (nur zum Mergen neuer Releases).

### Branch-Namen
```
feat/<kurz>      Feature        z.B. feat/landing-page
fix/<kurz>       Bugfix         z.B. fix/german-default-lang
chore/<kurz>     Infra/Doku
upstream-merge/<ver>   Upstream-Release einpflegen
```

### Commits (Conventional Commits — wie Upstream)
```
<type>(<scope>): <subject>      # type: feat|fix|docs|style|refactor|test|chore|ci
```
- Imperativ, Header ≤ 72 Zeichen, Englisch (Konsistenz mit Upstream).
- KI-Beitrag kennzeichnen: Footer `Assisted-by: Claude:<model>`.
- Eigene Core-Edits zusätzlich im Code mit `// ZHL:` markieren.

## Standard-Ablauf
```bash
git checkout zhl-main && git pull
git checkout -b feat/meine-aufgabe
# arbeiten, committen ...
git push -u origin feat/meine-aufgabe
gh pr create --base zhl-main --fill
```
Nach Review/grünem CI → **Squash- oder Rebase-Merge** in `zhl-main` (keine Merge-Commits).

## Upstream-Release einpflegen
```bash
git fetch upstream --tags
git checkout -b upstream-merge/5.2.0 zhl-main
git merge v5.2.0           # Konflikte v.a. an `// ZHL:`-Stellen
# testen, dann PR gegen zhl-main
```

## Lokale Checks vor dem Push (Upstream-Tooling)
```bash
./ci/ci-phplint            # PHP-Syntax
composer phpcsfixer:lint   # PSR-12
composer phpstan           # statische Analyse
composer phpunit           # Tests
```

## Niemals committen
`.env`, `config/config.php` mit echten Secrets, DB-Dumps, `tpl_c/`, `uploads/`.
