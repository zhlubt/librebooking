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
     * Klartext-Block für die Bestätigungs-Mail. Leerer String, wenn nichts mit Info-Link dabei ist.
     * @param array<int,array{device:string,type:string,url:string,text:string}> $infos
     */
    public static function EmailBlock(array $infos): string
    {
        $withLink = array_values(array_filter($infos, static fn ($i) => trim((string)$i['url']) !== ''));
        if (empty($withLink)) {
            return '';
        }
        $lines = [
            'Informieren Sie sich jetzt über die gebuchten Materialien und Medien:',
            '',
        ];
        foreach ($withLink as $i) {
            $line = '• ' . $i['device'] . ' → ' . $i['url'];
            $lines[] = $line;
            if (trim((string)$i['text']) !== '') {
                $lines[] = '   ' . $i['text'];
            }
        }
        return implode("\n", $lines);
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
