<?php

/**
 * ZHL — gemeinsame, gebrandete HTML-Layoutvorlage für Ausleih-Mails.
 *
 * Extrahiert aus dem Wochenreport-Design ([[ZhlEinfuehrungReport]]): grüner Kopf (#009260),
 * weiße abgerundete Karte auf grauem Grund, dezenter Footer. E-Mail-sicher (Tabellen + Inline-CSS,
 * kein <style>, keine externen Assets), damit es in Outlook/Gmail/Apple Mail konsistent aussieht.
 *
 * Nutzung:
 *   ZhlEmailLayout::Wrap([
 *       'eyebrow'  => 'ZHL Medienausleihe',        // kleine Zeile über dem Titel
 *       'title'    => 'Deine Buchung ist da',       // Kopf-Überschrift
 *       'subtitle' => 'Buchungsnummer 12345',       // optional, unter dem Titel
 *       'bodyHtml' => '<p>…</p>' . $liste,          // beliebiger HTML-Inhalt
 *       'footer'   => 'Automatische Mail …',        // optional, HTML erlaubt
 *   ]);
 */
class ZhlEmailLayout
{
    private const GREEN = '#009260';

    /** @param array{eyebrow?:string,title?:string,subtitle?:string,bodyHtml?:string,footer?:string} $p */
    public static function Wrap(array $p): string
    {
        $h = static fn ($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $eyebrow = trim((string)($p['eyebrow'] ?? 'ZHL Medienausleihe'));
        $title = trim((string)($p['title'] ?? ''));
        $subtitle = trim((string)($p['subtitle'] ?? ''));
        $bodyHtml = (string)($p['bodyHtml'] ?? '');
        $footer = trim((string)($p['footer'] ?? 'Automatische Nachricht der ZHL-Medienausleihe · Universität Bayreuth.'));

        $head = '<tr><td style="background:' . self::GREEN . ';padding:26px 28px;">';
        if ($eyebrow !== '') {
            $head .= '<div style="font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#d8f0e6;margin-bottom:6px;">' . $h($eyebrow) . '</div>';
        }
        if ($title !== '') {
            $head .= '<div style="font-size:21px;font-weight:700;color:#ffffff;">' . $h($title) . '</div>';
        }
        if ($subtitle !== '') {
            $head .= '<div style="font-size:13px;color:#d8f0e6;margin-top:6px;">' . $h($subtitle) . '</div>';
        }
        $head .= '</td></tr>';

        return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>'
            . '<body style="margin:0;padding:0;background:#eef1f0;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#eef1f0;"><tr><td align="center" style="padding:24px 12px 48px;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="620" style="max-width:620px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;font-family:-apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#1f2937;">'
            . $head
            . '<tr><td style="padding:24px 28px;font-size:15px;line-height:1.6;color:#1f2937;">' . $bodyHtml . '</td></tr>'
            . '<tr><td style="padding:18px 28px 26px;border-top:1px solid #eef1f0;color:#9099a3;font-size:11px;line-height:1.6;">' . $footer . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    /**
     * Bequemer Wrapper für die bestehenden KLARTEXT-Mails: hebt einen Plaintext-Body ins gebrandete
     * Layout (Leerzeilen → Absätze, Zeilenumbrüche → <br>, http(s)-Links werden klickbar). So bekommen
     * alle ZHL-Ausleih-Mails dieselbe Optik, ohne ihren Inhalt umzuschreiben.
     * @param array{eyebrow?:string,title?:string,subtitle?:string,text?:string,footer?:string} $p
     */
    public static function WrapText(array $p): string
    {
        $blocks = preg_split('/\n[ \t]*\n/', trim((string)($p['text'] ?? '')));
        $bodyHtml = '';
        foreach ($blocks as $block) {
            if (trim($block) === '') {
                continue;
            }
            $bodyHtml .= '<p style="margin:0 0 14px;">' . self::linkify($block) . '</p>';
        }
        $q = $p;
        unset($q['text']);
        $q['bodyHtml'] = $bodyHtml;
        return self::Wrap($q);
    }

    /**
     * Escaped den Text und macht http(s)-URLs klickbar (nachlaufende Satzzeichen bleiben außerhalb des
     * Links). Einzelne Zeilenumbrüche → <br>. Reihenfolge: erst escapen (XSS-sicher), dann verlinken.
     */
    private static function linkify(string $text): string
    {
        $esc = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $esc = preg_replace_callback('~https?://[^\s<]+~u', static function ($m) {
            $url = $m[0];
            $trail = '';
            // Nachlaufende Satz-/Klammerzeichen nicht in den Link ziehen.
            while ($url !== '' && strpos('.,;:!?)]', substr($url, -1)) !== false) {
                $trail = substr($url, -1) . $trail;
                $url = substr($url, 0, -1);
            }
            return '<a href="' . $url . '" style="color:#009260;font-weight:600;">' . $url . '</a>' . $trail;
        }, $esc);
        return nl2br($esc);
    }
}
