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
            $cn = self::paramVal((string)($d['organizer_name'] ?? $orgEmail));
            $lines[] = 'ORGANIZER;CN=' . $cn . ':mailto:' . $orgEmail;
        }
        foreach (($d['attendees'] ?? []) as $att) {
            $aEmail = trim((string)($att[1] ?? ''));
            if ($aEmail === '') {
                continue;
            }
            $aName = self::paramVal((string)($att[0] ?? $aEmail));
            $lines[] = 'ATTENDEE;CN=' . $aName . ';ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=FALSE:mailto:' . $aEmail;
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

    /** RFC-5545-Text-Escaping für Property-Werte (TEXT). */
    private static function esc(string $s): string
    {
        $s = str_replace('\\', '\\\\', $s);
        $s = str_replace([';', ','], ['\\;', '\\,'], $s);
        $s = str_replace(["\r\n", "\r", "\n"], '\\n', $s);
        return $s;
    }

    /**
     * Parameterwert (z. B. CN). Enthält der Wert ;,:" oder Steuerzeichen, wird er in DQUOTE gesetzt;
     * enthaltene DQUOTE/Newlines werden entfernt (RFC 5545 erlaubt kein " im quoted string).
     */
    private static function paramVal(string $s): string
    {
        $s = str_replace(["\r", "\n", '"'], ' ', $s);
        if (preg_match('/[;:,]/', $s)) {
            return '"' . $s . '"';
        }
        return $s;
    }

    /**
     * Content-Line-Folding auf max. 75 Oktette, OHNE Multibyte-Zeichen zu zerschneiden
     * (Fortsetzungszeilen mit führendem Space). Zählt Bytes, bricht aber nur an Zeichengrenzen.
     */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return $line; // im Zweifel ungefaltet (gültiges UTF-8 vorausgesetzt)
        }
        $out = '';
        $chunk = '';
        $first = true;
        foreach ($chars as $ch) {
            // 73 Byte je Segment, damit Folge-Segmente (mit führendem Space) sicher <= 75 bleiben.
            if (strlen($chunk) + strlen($ch) > 73) {
                $out .= ($first ? '' : "\r\n ") . $chunk;
                $first = false;
                $chunk = '';
            }
            $chunk .= $ch;
        }
        if ($chunk !== '') {
            $out .= ($first ? '' : "\r\n ") . $chunk;
        }
        return $out;
    }
}
