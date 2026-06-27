<?php

/**
 * ZHL — kleiner Key/Value-Settings-Speicher (Tabelle zhl_settings, Migration 028).
 *
 * Wird vom wöchentlichen Termine-Report (Schwellen, Empfänger, last-sent-Marker) genutzt und
 * von der Admin-Seite gepflegt. Bewusst über die LibreBooking-DB-Schicht (AdHocCommand), damit
 * es sowohl im Job-CLI (Domain/Access geladen) als auch in einer SecurePage funktioniert.
 */
class ZhlSettings
{
    public static function Get(string $key, string $default = ''): string
    {
        $cmd = new AdHocCommand('SELECT v FROM zhl_settings WHERE k = @key LIMIT 1');
        $cmd->AddParameter(new Parameter('@key', $key));
        $reader = ServiceLocator::GetDatabase()->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        return ($row && $row['v'] !== null) ? (string)$row['v'] : $default;
    }

    public static function GetInt(string $key, int $default = 0): int
    {
        $v = self::Get($key, (string)$default);
        return is_numeric($v) ? (int)$v : $default;
    }

    public static function Set(string $key, string $value): void
    {
        $cmd = new AdHocCommand(
            'INSERT INTO zhl_settings (k, v, updated_at) VALUES (@key, @val, @stamp) ' .
            'ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = VALUES(updated_at)'
        );
        $cmd->AddParameter(new Parameter('@key', $key));
        $cmd->AddParameter(new Parameter('@val', $value));
        $cmd->AddParameter(new Parameter('@stamp', gmdate('Y-m-d H:i:s')));
        ServiceLocator::GetDatabase()->Execute($cmd);
    }
}
