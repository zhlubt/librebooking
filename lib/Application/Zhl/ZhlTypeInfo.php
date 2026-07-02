<?php

/**
 * ZHL D2 — Info-Material je Geräte-Typ.
 *
 * Pro Geräte-Typ (type_label) kann ein Info-Link (Webseite/PDF/Video) + Info-Text in
 * zhl_type_info gepflegt werden. Diese Klasse liefert die Infos
 *  - als Map (type_label → [url,text]) für die Bundle-Anzeige und das Admin-UI,
 *  - aufgelöst je gebuchtem Gerät einer Reservierung (Erfolgsseite + Bestätigungs-Mail).
 *
 * Read-only; alle Methoden fangen DB-Fehler ab und liefern leere Ergebnisse, damit ein
 * fehlendes Info-System NIE eine Buchung oder Seite kippt.
 */
class ZhlTypeInfo
{
    /**
     * Aktive Info-Einträge je Geräte-Typ.
     * @return array<string,array{url:string,text:string}>  type_label → [url,text]
     */
    public static function Map($db): array
    {
        $map = [];
        try {
            $reader = $db->Query(new AdHocCommand(
                'SELECT type_label, info_url, info_text FROM zhl_type_info WHERE active = 1'
            ));
            while ($row = $reader->GetRow()) {
                $label = trim((string)$row['type_label']);
                if ($label === '') {
                    continue;
                }
                $map[$label] = [
                    'url' => trim((string)($row['info_url'] ?? '')),
                    'text' => trim((string)($row['info_text'] ?? '')),
                ];
            }
            $reader->Free();
        } catch (Throwable $e) {
            Log::Error('ZHL-TypeInfo: Map fehlgeschlagen: %s', $e);
        }
        return $map;
    }

    /**
     * Gebuchte Geräte einer Reservierung mit der Info ihres Geräte-Typs.
     * Listet ALLE gebuchten Geräte; `url`/`text` sind leer, wenn für den Typ nichts gepflegt ist.
     * @return array<int,array{device:string,type:string,url:string,text:string}>
     */
    public static function ForReference($db, string $ref): array
    {
        $out = [];
        if (trim($ref) === '') {
            return $out;
        }
        try {
            // Geräte der Reservierung (gleiche Quelle wie ZhlBookingDetailPresenter).
            $cmd = new AdHocCommand(
                'SELECT rr.resource_id, r.name FROM reservation_instances ri ' .
                'JOIN reservation_resources rr ON rr.series_id = ri.series_id ' .
                'JOIN resources r ON r.resource_id = rr.resource_id ' .
                'WHERE ri.reference_number = @ref ORDER BY rr.resource_level_id, r.name'
            );
            $cmd->AddParameter(new Parameter('@ref', $ref));
            $devices = [];
            $reader = $db->Query($cmd);
            while ($row = $reader->GetRow()) {
                $devices[(int)$row['resource_id']] = (string)$row['name'];
            }
            $reader->Free();
            if (empty($devices)) {
                return $out;
            }

            $typeMap = self::TypeMapFor($db, array_keys($devices));
            $infoMap = self::Map($db);

            foreach ($devices as $rid => $name) {
                $type = $typeMap[$rid] ?? '';
                $info = ($type !== '' && isset($infoMap[$type])) ? $infoMap[$type] : ['url' => '', 'text' => ''];
                $out[] = [
                    'device' => $name,
                    'type' => $type,
                    'url' => $info['url'],
                    'text' => $info['text'],
                ];
            }
        } catch (Throwable $e) {
            Log::Error('ZHL-TypeInfo: ForReference(%s) fehlgeschlagen: %s', $ref, $e);
        }
        return $out;
    }

    /**
     * Info-Einträge auf EINEN pro Geräte-Typ (Kategorie) reduzieren. Mehrere Einheiten desselben
     * Typs (z. B. drei DJI Mics) teilen denselben typ-basierten Info-Link → sonst würde er mehrfach
     * gelistet. Label wird der Geräte-Typ (Kategorie), nicht der Einzelgeräte-Name.
     * Nur Einträge mit Info-Link; Reihenfolge = erstes Auftreten.
     * @param array<int,array{device:string,type:string,url:string,text:string}> $infos
     * @return array<int,array{label:string,url:string,text:string}>
     */
    public static function DedupByType(array $infos): array
    {
        $byKey = [];
        foreach ($infos as $i) {
            $url = trim((string)($i['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $type = trim((string)($i['type'] ?? ''));
            // Schlüssel = Kategorie (Typ); fehlt der Typ, fällt es auf die URL zurück (statt aufs
            // Einzelgerät), damit derselbe Link auch ohne Typ-Attribut nur einmal erscheint.
            $key = $type !== '' ? 'type:' . $type : 'url:' . $url;
            if (isset($byKey[$key])) {
                continue;
            }
            $byKey[$key] = [
                'label' => $type !== '' ? $type : trim((string)($i['device'] ?? '')),
                'url' => $url,
                'text' => trim((string)($i['text'] ?? '')),
            ];
        }
        return array_values($byKey);
    }

    /**
     * Klartext-Block für die Bestätigungs-Mail. Leerer String, wenn nichts mit Info-Link dabei ist.
     * @param array<int,array{device:string,type:string,url:string,text:string}> $infos
     */
    public static function EmailBlock(array $infos): string
    {
        $items = self::DedupByType($infos);
        if (empty($items)) {
            return '';
        }
        $lines = [
            'Informieren Sie sich jetzt über die gebuchten Materialien und Medien:',
            '',
        ];
        foreach ($items as $i) {
            $lines[] = '• ' . $i['label'] . ' → ' . $i['url'];
            if ($i['text'] !== '') {
                $lines[] = '   ' . $i['text'];
            }
        }
        return implode("\n", $lines);
    }

    /**
     * HTML-Kartenliste der Info-Materialien für die gebrandete Bestätigungs-Mail (ein Eintrag pro
     * Kategorie). Leerer String, wenn kein Info-Link vorhanden ist. E-Mail-sicheres Inline-CSS.
     * @param array<int,array{device:string,type:string,url:string,text:string}> $infos
     */
    public static function EmailListHtml(array $infos): string
    {
        $items = self::DedupByType($infos);
        if (empty($items)) {
            return '';
        }
        $h = static fn ($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $rows = '';
        foreach ($items as $i) {
            $text = $i['text'] !== ''
                ? '<div style="font-size:13px;color:#6b7280;margin-top:3px;">' . $h($i['text']) . '</div>'
                : '';
            $rows .= '<tr><td style="padding:12px 14px;border:1px solid #e5eae8;border-radius:10px;background:#f8faf9;">'
                . '<div style="font-size:15px;font-weight:700;color:#1f2937;">' . $h($i['label']) . '</div>'
                . $text
                . '<div style="margin-top:8px;"><a href="' . $h($i['url']) . '" style="display:inline-block;font-size:13px;font-weight:700;color:#ffffff;background:#009260;text-decoration:none;padding:8px 14px;border-radius:8px;">Anleitung ansehen</a></div>'
                . '</td></tr>'
                . '<tr><td style="height:10px;line-height:10px;font-size:0;">&nbsp;</td></tr>';
        }
        return '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;width:100%;">' . $rows . '</table>';
    }

    /**
     * resource_id → Geräte-Typ (Attr „Geräte-Typ", Kategorie 4) für eine ID-Liste.
     * @param int[] $ids
     * @return array<int,string>
     */
    private static function TypeMapFor($db, array $ids): array
    {
        $map = [];
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) {
            return $map;
        }
        // IDs sind app-intern (aus der Reservierung), kein User-Input → sichere Inline-Liste.
        $in = implode(',', $ids);
        $cmd = new AdHocCommand(
            'SELECT v.entity_id AS rid, v.attribute_value AS t FROM custom_attribute_values v ' .
            'JOIN custom_attributes a ON a.custom_attribute_id = v.custom_attribute_id ' .
            'WHERE a.display_label = @l AND a.attribute_category = @c AND v.entity_id IN (' . $in . ')'
        );
        $cmd->AddParameter(new Parameter('@l', 'Geräte-Typ'));
        $cmd->AddParameter(new Parameter('@c', CustomAttributeCategory::RESOURCE));
        $reader = $db->Query($cmd);
        while ($row = $reader->GetRow()) {
            $val = trim((string)$row['t']);
            if ($val !== '') {
                $map[(int)$row['rid']] = $val;
            }
        }
        $reader->Free();
        return $map;
    }
}
