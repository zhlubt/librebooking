# Arbeitspakete (abgeleitet aus FEATURES.md)

> Konkrete, je 1 Branch/PR. Reihenfolge nach Wert/Aufwand. Custom-Pakete erst durchs
> Codex-Gate. Aktueller Stand im [PROGRESS.md](PROGRESS.md).

## Sofort — Config-Quick-Wins (kein/kaum Code, je 1 PR)
| WP | Inhalt | Features | Aufwand |
|---|---|---|---|
| QW-1 | Deutsche Default-Sprache | F26 | ✅ in PR #1 |
| QW-2 | Domain-Restriction (uni-bayreuth.de) + E-Mail-Aktivierung | F1 | XS |
| QW-3 | Reminder aktivieren + Cron (`sendreminders`) | F23 | S |
| QW-4 | Waitlist + Missed-Checkin-Cron einschalten | F36, F34 | S |
| QW-5 | DE-Mailtemplates (`lang/de_de/<Event>-custom.tpl`) | F22 | S |
| QW-6 | iCal-Subscription / API gezielt freigeben | F20, F28 | XS |

## UX / Frontpage (Säule 3)
| WP | Inhalt | Features | Aufwand |
|---|---|---|---|
| UX-1 | Landing-Page für anonyme Besucher verdrahten (Redirect/Rewrite) | F27 | S |
| UX-2 | ZHL-Branding via `css.extension.file` (Tokens: Grün #009260) + Styling-Plugin | — | M |
| UX-3 | Standard-User-Ansicht vereinfachen (Komplexität verstecken) | — | M |
| UX-4 | Begriffe vereinfachen via `lang-overrides.php` („Resource"→„Gerät") | F26 | S |

## Custom-Module (Säule 4, je nach Bedarf; vor Bau → Codex-Gate)
| WP | Inhalt | Features | Aufwand |
|---|---|---|---|
| CM-1 | **ZHL-Übergabe-Modul**: Personalverfügbarkeit-Slots + Abhol-/Rückgabe-Terminwahl | F16/F17, F8, F19 | L |
| CM-2 | **QR-Verifikations-Checkliste** im Check-in/out (nutzt Accessories) | F10, F30 | M |
| CM-3 | Accessory-Erweiterung: Zustand + Seriennummer | F30 | S |
| CM-4 | **Einweisungs-/Zertifikat-Lifecycle** (Gruppen-Gate nativ + Cron-Ablauf + Anfrage-Plugin) | F40 | M |
| CM-5 | Overdue-Erkennung (Rückgabe) + Eskalation + Auto-Sperre | F34 | M |
| CM-6 | Audit-Log (Tabelle + Schreib-Hooks + Admin-View) | F37 | M |
| CM-7 | DSGVO: User-Export + Anonymisierung statt Hard-Delete + Consent | F38 | M |
| CM-8 | Erweiterte Suche (Fuzzy/Synonym, Ressourcen-Autocomplete) | F25 | M |
| CM-9 | Webhooks via PostReservation-Plugin (HTTP-POST) | F24 | S |
| CM-10 | Ressourcen-Doku-Anhänge + echte MIME-Prüfung + Max-Size | F9 | S |

## Produktiv-Upgrade (Säule 1)
| WP | Inhalt | Aufwand |
|---|---|---|
| UP-1 | Wartungsfenster, finales Backup, 5.1.0-Deploy per SFTP, DB-Stempel, Smoke-Test | M |

## Vorgeschlagene nächste 3 PRs
1. **QW-2…QW-6** als ein „Config-Quick-Wins"-PR (sofortiger Nutzen, kaum Risiko).
2. **UX-1 + UX-2** (Landing verdrahten + Branding) — sichtbarer Fortschritt.
3. **CM-4 Stufe 1** (F40 Gruppen-Gate ohne Code an der Kopie durchspielen + dokumentieren).
