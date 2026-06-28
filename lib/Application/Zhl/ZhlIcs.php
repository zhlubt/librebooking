<?php

/**
 * ZHL — iCalendar (RFC 5545) Builder für Einführungstermine (SPEC-EINFUEHRUNG-AUSHANDLUNG §5).
 *
 * Erzeugt eine VCALENDAR/VEVENT als Klartext-String, der per
 * `EmailMessage::AddStringAttachment($ics, 'einfuehrung.ics')` an die Bestätigungs-/Storno-Mail
 * gehängt wird. v1 = einfacher .ics-Anhang (öffnet in allen Kalender-Apps), kein Outlook-Auto-RSVP.
 *
 * Einladung:  METHOD:REQUEST · STATUS:CONFIRMED · SEQUENCE:0
 * Absage:     METHOD:CANCEL  · STATUS:CANCELLED · SEQUENCE:1  (GLEICHE UID wie die Einladung!)
 *
 * Alle Werte werden RFC-escaped (\\ , ; \n) und auf 75 Oktette gefaltet; Zeilenenden sind CRLF.
 * UTC-Zeiten als ...Z. Datums-Eingaben sind 'Y-m-d H:i:s' in UTC.
 */
class ZhlIcs
{
    /**
     * @param array{
     *   uid:string, sequence:int, method:string,
     *   start_utc:string, end_utc:string,
     *   summary:string, description?:string, location?:string,
     *   organizer_name?:string, organizer_email?:string,
     *   attendees?: array<array{0:string,1:string}>
     * } $d
     */
    public static function Build(array $d): string
    {
        $method = strtoupper((string)($d['method'] ?? 'REQUEST'));
        $isCancel = $method === 'CANCEL';
        $dtStart = self::utc($d['start_utc']);
        $dtEnd = self::utc($d['end_utc']);
        $dtStamp = gmdate('Ymd\THis\Z');

        $lines = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//ZHL Medienausleihe//Einfuehrung//DE';
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:' . $method;
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . self::esc((string)$d['uid']);
        $lines[] = 'SEQUENCE:' . (int)($d['sequence'] ?? 0);
        $lines[] = 'DTSTAMP:' . $dtStamp;
        $lines[] = 'DTSTART:' . $dtStart;
        $lines[] = 'DTEND:' . $dtEnd;
        $lines[] = 'SUMMARY:' . self::esc((string)($d['summary'] ?? 'ZHL Einführung'));
        if (($d['description'] ?? '') !== '') {
            $lines[] = 'DESCRIPTION:' . self::esc((string)$d['description']);
        }
        if (($d['location'] ?? '') !== '') {
            $lines[] = 'LOCATION:' . self::esc((string)$d['location']);
        }
        $orgEmail = trim((string)($d['organizer_email'] ?? ''));
        if ($orgEmail !== '') {
            $cn = self::esc((string)($d['organizer_name'] ?? $orgEmail));
            $lines[] = 'ORGANIZER;CN=' . $cn . ':mailto:' . $orgEmail;
        }
        foreach (($d['attendees'] ?? []) as $att) {
            $aEmail = trim((string)($att[1] ?? ''));
            if ($aEmail === '') {
                continue;
            }
            $aName = self::esc((string)($att[0] ?? $aEmail));
            $part = $isCancel ? 'NEEDS-ACTION' : 'NEEDS-ACTION';
            $lines[] = 'ATTENDEE;CN=' . $aName . ';ROLE=REQ-PARTICIPANT;PARTSTAT=' . $part . ';RSVP=FALSE:mailto:' . $aEmail;
        }
        $lines[] = 'STATUS:' . ($isCancel ? 'CANCELLED' : 'CONFIRMED');
        if ($isCancel) {
            $lines[] = 'TRANSP:TRANSPARENT';
        }
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        // Folding + CRLF.
        $folded = array_map([self::class, 'fold'], $lines);
        return implode("\r\n", $folded) . "\r\n";
    }

    /** 'Y-m-d H:i:s' (UTC) → 'YmdTHisZ'. Fällt bei Parsefehler auf jetzt zurück. */
    private static function utc(string $ymdhis): string
    {
        $ts = strtotime($ymdhis . ' UTC');
        if ($ts === false) {
            $ts = time();
        }
        return gmdate('Ymd\THis\Z', $ts);
    }

    /** RFC-5545-Text-Escaping für Property-Werte. */
    private static function esc(string $s): string
    {
        $s = str_replace('\\', '\\\\', $s);
        $s = str_replace([';', ','], ['\\;', '\\,'], $s);
        $s = str_replace(["\r\n", "\r", "\n"], '\\n', $s);
        return $s;
    }

    /** Content-Line-Folding auf 75 Oktette (Fortsetzungszeilen mit führendem Space). */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        $chunk = '';
        $len = strlen($line);
        for ($i = 0; $i < $len; $i++) {
            $chunk .= $line[$i];
            // Auf 74 begrenzen, damit mit dem späteren CRLF + Space sauber gefaltet wird.
            if (strlen($chunk) >= 74) {
                $out .= ($out === '' ? '' : "\r\n ") . $chunk;
                $chunk = '';
            }
        }
        if ($chunk !== '') {
            $out .= ($out === '' ? '' : "\r\n ") . $chunk;
        }
        return $out;
    }
}
