<?php

/**
 * ZHL D3 — vertrauliche Zusatz-Infos je Zertifikats-Typ (Transponder-Code, News, Dokument-Link).
 *
 * SICHERHEIT: `ForUser()` ist das maßgebliche serverseitige Gate. Es liefert die vertraulichen
 * Felder NUR für Zertifikate, die der eingeloggte Nutzer SELBST per gültigem (nicht abgelaufenem)
 * Grant besitzt — und nur, wenn der Zertifikatstyp UND die Info aktiv sind. Inhalte werden NIE in
 * E-Mails oder Logs geschrieben. `Map()` (alle Typen) ist ausschließlich für den Admin-Editor.
 *
 * Read-only; alle Methoden fangen DB-Fehler ab und liefern leere Ergebnisse (kein Leak, kein 500).
 */
class ZhlCertTypeInfo
{
    /**
     * Vertrauliche Infos je Zertifikats-Typ, den DIESER Nutzer mit gültigem Grant hält.
     * @return array<int,array{transponder_code:string,news_text:string,doc_url:string}>
     *         cert_type_id → Felder (nur Einträge mit mind. einem nicht-leeren Feld)
     */
    public static function ForUser($db, int $userId): array
    {
        $out = [];
        if ($userId <= 0) {
            return $out;
        }
        try {
            $cmd = new AdHocCommand(
                'SELECT i.cert_type_id, i.transponder_code, i.news_text, i.doc_url ' .
                'FROM zhl_cert_grant g ' .
                'JOIN zhl_cert_type t ON t.id = g.cert_type_id AND t.active = 1 ' .
                'JOIN zhl_cert_type_info i ON i.cert_type_id = g.cert_type_id AND i.active = 1 ' .
                'WHERE g.user_id = @uid AND (g.expires_at IS NULL OR g.expires_at > @now)'
            );
            $cmd->AddParameter(new Parameter('@uid', $userId));
            $cmd->AddParameter(new Parameter('@now', gmdate('Y-m-d H:i:s')));
            $reader = $db->Query($cmd);
            while ($row = $reader->GetRow()) {
                $code = trim((string)($row['transponder_code'] ?? ''));
                $news = trim((string)($row['news_text'] ?? ''));
                $doc = trim((string)($row['doc_url'] ?? ''));
                if ($code === '' && $news === '' && $doc === '') {
                    continue;
                }
                $out[(int)$row['cert_type_id']] = [
                    'transponder_code' => $code,
                    'news_text' => $news,
                    'doc_url' => $doc,
                ];
            }
            $reader->Free();
        } catch (Throwable $e) {
            Log::Error('ZHL-CertTypeInfo: ForUser(%d) fehlgeschlagen: %s', $userId, $e);
        }
        return $out;
    }

    /**
     * Zugangsdaten (Code/News/Doc) je GERÄT für DIESEN Nutzer — gegated wie ForUser: nur Geräte,
     * deren abdeckendes Zertifikat der Nutzer mit gültigem (nicht abgelaufenem) Grant hält und für
     * das aktive Infos gepflegt sind. Für die Buchungsdetail-Seite („Zugangsdaten zum gebuchten Gerät").
     * @param int[] $resourceIds gebuchte Geräte-IDs (Filter; app-intern, kein User-Input)
     * @return array<int,array{transponder_code:string,news_text:string,doc_url:string}> resource_id → Felder
     */
    public static function ForUserByResource($db, int $userId, array $resourceIds): array
    {
        $out = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $resourceIds), static fn ($v) => $v > 0)));
        if ($userId <= 0 || empty($ids)) {
            return $out;
        }
        try {
            $in = implode(',', $ids); // nur ints (intval), sichere Inline-Liste
            $cmd = new AdHocCommand(
                'SELECT ctr.resource_id, i.transponder_code, i.news_text, i.doc_url ' .
                'FROM zhl_cert_grant g ' .
                'JOIN zhl_cert_type t ON t.id = g.cert_type_id AND t.active = 1 ' .
                'JOIN zhl_cert_type_info i ON i.cert_type_id = g.cert_type_id AND i.active = 1 ' .
                'JOIN zhl_cert_type_resource ctr ON ctr.cert_type_id = g.cert_type_id ' .
                'WHERE g.user_id = @uid AND (g.expires_at IS NULL OR g.expires_at > @now) ' .
                'AND ctr.resource_id IN (' . $in . ')'
            );
            $cmd->AddParameter(new Parameter('@uid', $userId));
            $cmd->AddParameter(new Parameter('@now', gmdate('Y-m-d H:i:s')));
            $reader = $db->Query($cmd);
            while ($row = $reader->GetRow()) {
                $rid = (int)$row['resource_id'];
                if (isset($out[$rid])) {
                    continue; // erstes abdeckendes Zertifikat gewinnt
                }
                $code = trim((string)($row['transponder_code'] ?? ''));
                $news = trim((string)($row['news_text'] ?? ''));
                $doc = trim((string)($row['doc_url'] ?? ''));
                if ($code === '' && $news === '' && $doc === '') {
                    continue;
                }
                $out[$rid] = ['transponder_code' => $code, 'news_text' => $news, 'doc_url' => $doc];
            }
            $reader->Free();
        } catch (Throwable $e) {
            Log::Error('ZHL-CertTypeInfo: ForUserByResource(%d) fehlgeschlagen: %s', $userId, $e);
        }
        return $out;
    }

    /**
     * Alle gepflegten Infos je Zertifikats-Typ — NUR für den Admin-Editor (kein Nutzer-Gate!).
     * @return array<int,array{transponder_code:string,news_text:string,doc_url:string,active:int}>
     */
    public static function Map($db): array
    {
        $map = [];
        try {
            $reader = $db->Query(new AdHocCommand(
                'SELECT cert_type_id, transponder_code, news_text, doc_url, active FROM zhl_cert_type_info'
            ));
            while ($row = $reader->GetRow()) {
                $map[(int)$row['cert_type_id']] = [
                    'transponder_code' => (string)($row['transponder_code'] ?? ''),
                    'news_text' => (string)($row['news_text'] ?? ''),
                    'doc_url' => (string)($row['doc_url'] ?? ''),
                    'active' => (int)($row['active'] ?? 0),
                ];
            }
            $reader->Free();
        } catch (Throwable $e) {
            Log::Error('ZHL-CertTypeInfo: Map fehlgeschlagen: %s', $e);
        }
        return $map;
    }
}
