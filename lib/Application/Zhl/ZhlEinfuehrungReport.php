<?php

require_once(ROOT_DIR . 'Presenters/ZhlTerminplaner.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlSettings.php');

/**
 * ZHL — Wöchentlicher Verfügbarkeits-Report für Einführungs-/Übergabetermine.
 *
 * Build() zieht die ANGEBOTENEN (buchbaren) Slots aus dem Terminplaner (meet.zhl-ubt.de) für die
 * laufende Woche + die nächsten 3 (4 Kalenderwochen), je Kategorie:
 *   - „Einführung in Medien", „Einführung ins Videostudio", „Übergabe Medien".
 * und aggregiert: pro Woche & Kategorie (Tage mit Terminen / Termine), pro anbietender Person,
 * sowie eine Abdeckungs-Heatmap (Wochentag × Tageszeit) je Woche.
 *
 * RenderHtml() rendert daraus die fertige Mail (inline-Styles → Outlook-tauglich). Wiederverwendet
 * von Jobs/zhl_einfuehrung_report.php (Versand) und der Admin-Vorschau.
 */
class ZhlEinfuehrungReport
{
    /** Überwachte Kategorien (Terminplaner-Typ-Label). */
    public const CATEGORIES = [
        ['key' => 'medien',    'label' => 'Einführung in Medien',        'color' => '#0ea5a0'],
        ['key' => 'studio',    'label' => 'Einführung ins Videostudio',  'color' => '#7c4dff'],
        ['key' => 'uebergabe', 'label' => 'Übergabe Medien',             'color' => '#0284c7'],
    ];

    /** Tageszeit-Blöcke (Stunden-Grenzen, lokale Zeit). */
    public const TIMEBLOCKS = [
        ['label' => 'Vormittag',  'from' => 8,  'to' => 12],
        ['label' => 'Mittag',     'from' => 12, 'to' => 14],
        ['label' => 'Nachmittag', 'from' => 14, 'to' => 17],
        ['label' => 'Abend',      'from' => 17, 'to' => 20],
    ];

    public const WEEKDAYS = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

    /**
     * Baut die Report-Datenstruktur. @return array (siehe RenderHtml für die Felder).
     */
    public static function Build(int $weeks = 4): array
    {
        $tz = new DateTimeZone('Europe/Berlin');
        $utc = new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $tz);
        $dow = (int)$now->format('N');                       // 1=Mo … 7=So
        $monday = $now->setTime(0, 0, 0)->modify('-' . ($dow - 1) . ' days');
        $windowEnd = $monday->modify('+' . ($weeks * 7) . ' days');

        $minDays = max(0, ZhlSettings::GetInt('einf_report_min_days', 1));
        $minAppts = max(0, ZhlSettings::GetInt('einf_report_min_appts', 1));
        $recipient = ZhlSettings::Get('einf_report_recipient', 'zhlmedien@uni-bayreuth.de');

        // Wochen-Gerüst vorbereiten.
        $weeksData = [];
        for ($w = 0; $w < $weeks; $w++) {
            $ws = $monday->modify('+' . ($w * 7) . ' days');
            $perCat = [];
            foreach (self::CATEGORIES as $c) {
                $perCat[$c['key']] = ['days' => [], 'count' => 0];
            }
            $weeksData[$w] = [
                'kw' => $ws->format('W'),
                'start' => $ws,
                'end' => $ws->modify('+6 days'),
                'perCat' => $perCat,
                'hm' => array_fill(0, 7, array_fill(0, count(self::TIMEBLOCKS), 0)),
            ];
        }

        $offerers = [];
        $apiError = false;

        foreach (self::CATEGORIES as $cat) {
            $resp = ZhlTerminplaner::Request('GET', '/api/lesson_slots.php', ['type_label' => $cat['label']]);
            if ($resp === null || !isset($resp['members'])) {
                $apiError = true;
                continue;
            }
            foreach ($resp['members'] as $mem) {
                $name = trim((string)($mem['member_name'] ?? ''));
                if ($name === '') {
                    $name = 'Mitglied ' . (string)($mem['member_id'] ?? '?');
                }
                foreach (($mem['slots'] ?? []) as $s) {
                    $startUtc = (string)($s['start_utc'] ?? '');
                    if ($startUtc === '') {
                        continue;
                    }
                    try {
                        $local = (new DateTimeImmutable($startUtc, $utc))->setTimezone($tz);
                    } catch (Exception $e) {
                        continue;
                    }
                    if ($local < $monday || $local >= $windowEnd) {
                        continue;
                    }
                    $daysDiff = (int)$monday->diff($local->setTime(0, 0, 0))->days;
                    $wi = intdiv($daysDiff, 7);
                    if ($wi < 0 || $wi >= $weeks) {
                        continue;
                    }
                    $weeksData[$wi]['perCat'][$cat['key']]['days'][$local->format('Y-m-d')] = true;
                    $weeksData[$wi]['perCat'][$cat['key']]['count']++;
                    $weeksData[$wi]['hm'][(int)$local->format('N') - 1][self::blockIndex((int)$local->format('G'))]++;

                    if (!isset($offerers[$name])) {
                        $offerers[$name] = ['name' => $name, 'total' => 0, 'cats' => []];
                    }
                    $offerers[$name]['total']++;
                    $offerers[$name]['cats'][$cat['label']] = true;
                }
            }
        }

        // Wochen finalisieren + Status.
        $weeksUnder = [];
        $weeksOut = [];
        foreach ($weeksData as $wd) {
            $anyBad = false;
            $cats = [];
            foreach (self::CATEGORIES as $cat) {
                $days = count($wd['perCat'][$cat['key']]['days']);
                $cnt = $wd['perCat'][$cat['key']]['count'];
                $status = self::status($days, $cnt, $minDays, $minAppts);
                if ($status === 'bad') {
                    $anyBad = true;
                }
                $cats[$cat['key']] = ['days' => $days, 'count' => $cnt, 'status' => $status];
            }
            if ($anyBad) {
                $weeksUnder[] = 'KW ' . $wd['kw'];
            }
            $weeksOut[] = [
                'kw' => $wd['kw'],
                'range' => $wd['start']->format('d.m.') . '–' . $wd['end']->format('d.m.'),
                'cats' => $cats,
                'hm' => $wd['hm'],
                'anyBad' => $anyBad,
            ];
        }

        $offerersOut = array_values($offerers);
        usort($offerersOut, fn($a, $b) => $b['total'] <=> $a['total']);
        foreach ($offerersOut as &$o) {
            $o['cats'] = array_keys($o['cats']);
        }
        unset($o);

        $firstKw = $weeksOut[0]['kw'] ?? $monday->format('W');
        $lastKw = $weeksOut[count($weeksOut) - 1]['kw'] ?? $firstKw;

        return [
            'generatedAt' => $now->format('d.m.Y, H:i'),
            'windowLabel' => 'KW ' . $firstKw . '–' . $lastKw . ' (' . $monday->format('d.m.') . '–' . $windowEnd->modify('-1 day')->format('d.m.Y') . ')',
            'minDays' => $minDays,
            'minAppts' => $minAppts,
            'recipient' => $recipient,
            'categories' => self::CATEGORIES,
            'weeks' => $weeksOut,
            'offerers' => $offerersOut,
            'weeksUnder' => $weeksUnder,
            'apiError' => $apiError,
            'hint' => self::coverageHint($weeksOut),
        ];
    }

    private static function blockIndex(int $hour): int
    {
        foreach (self::TIMEBLOCKS as $i => $b) {
            if ($hour < $b['to']) {
                return $i;
            }
        }
        return count(self::TIMEBLOCKS) - 1; // alles >= letzter Block-Endwert → Abend
    }

    private static function status(int $days, int $count, int $minDays, int $minAppts): string
    {
        if ($days < $minDays || $count < $minAppts) {
            return 'bad';
        }
        if ($days <= $minDays || $count <= $minAppts) {
            return 'warn';
        }
        return 'ok';
    }

    private static function coverageHint(array $weeks): string
    {
        $byBlock = array_fill(0, count(self::TIMEBLOCKS), 0);
        $byDay = array_fill(0, 7, 0);
        $total = 0;
        foreach ($weeks as $wk) {
            foreach ($wk['hm'] as $d => $blocks) {
                foreach ($blocks as $bi => $n) {
                    $byBlock[$bi] += $n;
                    $byDay[$d] += $n;
                    $total += $n;
                }
            }
        }
        if ($total === 0) {
            return 'Im gesamten Zeitraum sind keine Termine hinterlegt — bitte Termine anbieten.';
        }
        $minBi = 0;
        foreach ($byBlock as $bi => $n) {
            if ($n < $byBlock[$minBi]) {
                $minBi = $bi;
            }
        }
        $minD = 0;
        foreach (range(0, 4) as $d) { // nur Mo–Fr für die Empfehlung
            if ($byDay[$d] < $byDay[$minD]) {
                $minD = $d;
            }
        }
        return 'Am dünnsten besetzt: ' . self::TIMEBLOCKS[$minBi]['label'] . ' und ' . self::WEEKDAYS[$minD]
            . ' — hier würden zusätzliche Termine die Abdeckung am meisten verbessern.';
    }

    /**
     * Rendert die fertige Report-Mail als HTML (inline-Styles, tabellenbasiert → Outlook-tauglich).
     */
    public static function RenderHtml(array $r): string
    {
        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

        $pill = function (string $st): string {
            $map = [
                'ok'   => ['ausreichend',     '#e7f6ed', '#1f8a4c'],
                'warn' => ['gerade am Limit', '#fdf3e0', '#9a6b00'],
                'bad'  => ['zu wenig',        '#fdecea', '#c0392b'],
            ];
            [$t, $bg, $fg] = $map[$st] ?? $map['bad'];
            return '<span style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;background:' . $bg . ';color:' . $fg . ';white-space:nowrap;">' . $t . '</span>';
        };
        $heat = function (int $n): string {
            if ($n <= 0) {
                $bg = '#fbe9e7'; $fg = '#c0392b';
            } elseif ($n === 1) {
                $bg = '#fff6df'; $fg = '#9a6b00';
            } elseif ($n === 2) {
                $bg = '#dbefe1'; $fg = '#1f8a4c';
            } else {
                $bg = '#9ed9b4'; $fg = '#0c5c34';
            }
            return '<td style="border:1px solid #eef1f0;padding:6px 3px;text-align:center;font-weight:700;font-size:11px;background:' . $bg . ';color:' . $fg . ';">' . $n . '</td>';
        };

        $td = 'padding:9px 11px;border-bottom:1px solid #eef1f0;font-size:13px;';
        $tdNum = $td . 'text-align:center;font-weight:700;';
        $th = 'padding:9px 11px;background:#f8faf9;font-size:11px;letter-spacing:.04em;text-transform:uppercase;color:#6b7280;text-align:left;';

        // --- Kopf / Banner ---
        $bannerBad = !empty($r['weeksUnder']);
        if ($bannerBad) {
            $banner = '<div style="border-radius:10px;padding:14px 16px;font-size:14px;line-height:1.5;background:#fdecea;border:1px solid #f5c6c0;color:#8a1f12;">'
                . '<b>⚠️ ' . count($r['weeksUnder']) . ' von ' . count($r['weeks']) . ' Wochen unterschreiten das Mindestziel.</b><br>'
                . 'Betroffen: <b>' . $h(implode(', ', $r['weeksUnder'])) . '</b> — in mindestens einer Kategorie zu wenig Termine. '
                . 'Ausleihen mit Einführungs-/Übergabepflicht sind dann blockiert.</div>';
        } else {
            $banner = '<div style="border-radius:10px;padding:14px 16px;font-size:14px;line-height:1.5;background:#e7f6ed;border:1px solid #b9e3c8;color:#1f6b3e;">'
                . '<b>✅ Alle ' . count($r['weeks']) . ' Wochen erfüllen das Mindestziel</b> in allen Kategorien.</div>';
        }
        $apiNote = !empty($r['apiError'])
            ? '<div style="border-radius:10px;padding:10px 14px;font-size:12px;background:#fdf6e3;border:1px solid #efe0b3;color:#7a5b00;margin-top:10px;">Hinweis: Mindestens eine Kategorie konnte nicht vom Terminplaner geladen werden — Zahlen ggf. unvollständig.</div>'
            : '';

        // --- Status-Tabelle ---
        $rows = '';
        foreach ($r['weeks'] as $wk) {
            $first = true;
            foreach (self::CATEGORIES as $cat) {
                $c = $wk['cats'][$cat['key']];
                $rows .= '<tr>';
                if ($first) {
                    $rows .= '<td rowspan="' . count(self::CATEGORIES) . '" style="' . $td . 'border-top:2px solid #e5e9e8;vertical-align:top;font-weight:700;">KW ' . $h($wk['kw'])
                        . '<br><span style="font-weight:400;color:#6b7280;font-size:11px;">' . $h($wk['range']) . '</span></td>';
                }
                $topB = $first ? 'border-top:2px solid #e5e9e8;' : '';
                $rows .= '<td style="' . $td . $topB . '"><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:' . $cat['color'] . ';margin-right:7px;"></span>' . $h($cat['label']) . '</td>'
                    . '<td style="' . $tdNum . $topB . '">' . $c['days'] . '</td>'
                    . '<td style="' . $tdNum . $topB . '">' . $c['count'] . '</td>'
                    . '<td style="' . $td . $topB . '">' . $pill($c['status']) . '</td></tr>';
                $first = false;
            }
        }
        $statusTable = '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;width:100%;">'
            . '<tr><th style="' . $th . '">Woche</th><th style="' . $th . '">Kategorie</th>'
            . '<th style="' . $th . 'text-align:center;">Tage</th><th style="' . $th . 'text-align:center;">Termine</th><th style="' . $th . '">Status</th></tr>'
            . $rows . '</table>';

        // --- Anbieter ---
        $offHtml = '';
        if (empty($r['offerers'])) {
            $offHtml = '<div style="padding:10px 0;color:#c0392b;font-size:14px;">Im Zeitraum bietet niemand Termine an.</div>';
        } else {
            foreach ($r['offerers'] as $o) {
                $offHtml .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;width:100%;"><tr>'
                    . '<td style="padding:9px 0;border-bottom:1px solid #eef1f0;font-size:14px;"><b>' . $h($o['name']) . '</b> '
                    . '<span style="color:#6b7280;font-size:12px;">· ' . $h(implode(', ', $o['cats'])) . '</span></td>'
                    . '<td style="padding:9px 0;border-bottom:1px solid #eef1f0;font-size:14px;text-align:right;font-weight:700;color:#007a50;white-space:nowrap;">'
                    . (int)$o['total'] . ' Termine</td></tr></table>';
            }
        }

        // --- Heatmaps (eine je Woche, 2 pro Zeile) ---
        $hmCards = [];
        foreach ($r['weeks'] as $wk) {
            $tag = $wk['anyBad']
                ? '<span style="font-size:11px;font-weight:700;padding:1px 8px;border-radius:999px;background:#fdecea;color:#c0392b;margin-left:6px;">Lücken</span>'
                : '<span style="font-size:11px;font-weight:700;padding:1px 8px;border-radius:999px;background:#e7f6ed;color:#1f8a4c;margin-left:6px;">ok</span>';
            $head = '<tr><td style="border:1px solid #eef1f0;padding:6px 3px;background:#f8faf9;"></td>';
            foreach (self::WEEKDAYS as $wdl) {
                $head .= '<td style="border:1px solid #eef1f0;padding:6px 3px;text-align:center;font-size:11px;font-weight:700;color:#6b7280;background:#f8faf9;">' . $wdl . '</td>';
            }
            $head .= '</tr>';
            $body = '';
            foreach (self::TIMEBLOCKS as $bi => $blk) {
                $body .= '<tr><td style="border:1px solid #eef1f0;padding:6px 6px;text-align:right;font-size:11px;font-weight:600;color:#6b7280;white-space:nowrap;">' . $blk['label'] . '</td>';
                foreach (self::WEEKDAYS as $di => $wdl) {
                    $body .= $heat((int)$wk['hm'][$di][$bi]);
                }
                $body .= '</tr>';
            }
            $hmCards[] = '<h4 style="margin:0 0 6px;font-size:13px;">KW ' . $h($wk['kw']) . $tag . '</h4>'
                . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;width:100%;">' . $head . $body . '</table>';
        }
        // 2-spaltiges Raster
        $hmGrid = '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:separate;border-spacing:0 0;width:100%;"><tr>';
        $col = 0;
        foreach ($hmCards as $i => $card) {
            $hmGrid .= '<td style="vertical-align:top;padding:0 ' . ($col === 0 ? '9px' : '0') . ' 18px ' . ($col === 0 ? '0' : '9px') . ';width:50%;">' . $card . '</td>';
            $col++;
            if ($col === 2 && $i < count($hmCards) - 1) {
                $hmGrid .= '</tr><tr>';
                $col = 0;
            }
        }
        if ($col === 1) {
            $hmGrid .= '<td style="width:50%;"></td>';
        }
        $hmGrid .= '</tr></table>';

        $secTitle = fn($t) => '<p style="font-size:13px;letter-spacing:.05em;text-transform:uppercase;color:#6b7280;margin:28px 0 12px;font-weight:700;">' . $t . '</p>';

        // --- Dokument ---
        return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>'
            . '<body style="margin:0;padding:0;background:#eef1f0;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#eef1f0;"><tr><td align="center" style="padding:24px 12px 48px;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="680" style="max-width:680px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;font-family:-apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#1f2937;">'
            // Kopf
            . '<tr><td style="background:#009260;padding:26px 28px;">'
            . '<div style="font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#d8f0e6;margin-bottom:6px;">ZHL Medienausleihe · Wochenreport</div>'
            . '<div style="font-size:21px;font-weight:700;color:#ffffff;">Einführungs- &amp; Übergabetermine — nächste 4 Wochen</div>'
            . '<div style="font-size:13px;color:#d8f0e6;margin-top:6px;">Stand: ' . $h($r['generatedAt']) . ' · Zeitraum ' . $h($r['windowLabel']) . ' · Quelle: meet.zhl-ubt.de</div>'
            . '</td></tr>'
            // Body
            . '<tr><td style="padding:24px 28px;">'
            . $banner . $apiNote
            . $secTitle('Mindestziel pro Woche &amp; Kategorie')
            . '<div style="background:#f3f6f5;border:1px dashed #c2d6cf;border-radius:10px;padding:12px 16px;font-size:13px;color:#374151;">'
            . 'Jede Kategorie braucht pro Woche mindestens <b style="color:#007a50;">' . (int)$r['minDays'] . ' Tag(e) mit Terminen</b> und <b style="color:#007a50;">' . (int)$r['minAppts'] . ' Termin(e)</b>. '
            . '<span style="color:#9099a3;">(Im Admin änderbar.)</span></div>'
            . $secTitle('Status je Woche &amp; Kategorie') . $statusTable
            . $secTitle('Wer bietet an (nächste 4 Wochen)') . $offHtml
            . $secTitle('Abdeckung über die Woche (Tage × Tageszeit)') . $hmGrid
            . '<p style="font-size:11px;color:#6b7280;margin:4px 0 0;">Termine je Feld: '
            . '<span style="display:inline-block;width:11px;height:11px;border-radius:3px;background:#fbe9e7;vertical-align:middle;"></span> 0 '
            . '<span style="display:inline-block;width:11px;height:11px;border-radius:3px;background:#fff6df;vertical-align:middle;margin-left:8px;"></span> 1 '
            . '<span style="display:inline-block;width:11px;height:11px;border-radius:3px;background:#dbefe1;vertical-align:middle;margin-left:8px;"></span> 2 '
            . '<span style="display:inline-block;width:11px;height:11px;border-radius:3px;background:#9ed9b4;vertical-align:middle;margin-left:8px;"></span> 3+ '
            . '· Vormittag 08–12, Mittag 12–14, Nachmittag 14–17, Abend 17–20 Uhr.</p>'
            . '<div style="border-radius:10px;padding:13px 16px;font-size:13px;line-height:1.5;background:#e7f6ed;border:1px solid #b9e3c8;color:#1f6b3e;margin-top:18px;"><b>Tipp aus den Daten:</b> ' . $h($r['hint']) . '</div>'
            . '</td></tr>'
            // Footer
            . '<tr><td style="padding:18px 28px 26px;border-top:1px solid #eef1f0;color:#9099a3;font-size:11px;line-height:1.6;">'
            . 'Automatischer Wochenreport der ZHL-Medienausleihe · jeden Montag früh an ' . $h($r['recipient']) . '.<br>'
            . 'Datenquelle: angebotene Termine auf meet.zhl-ubt.de. Mindestwerte &amp; Empfänger im Admin konfigurierbar.'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}
