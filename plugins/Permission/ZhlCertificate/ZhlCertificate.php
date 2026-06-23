<?php
/**
 * F40 Stufe 2 — Zertifikat-Ablauf (cron-frei).
 *
 * Decorator über den nativen PermissionService: Für zertifikatspflichtige Geräte
 * (Einträge in zhl_certificate_required) reicht die Gruppen-Berechtigung (Stufe 1)
 * NICHT mehr — zusätzlich muss ein GÜLTIGER (nicht abgelaufener) Eintrag in
 * zhl_certificate vorliegen. Die Prüfung passiert beim Buchen, daher KEIN Cron nötig.
 *
 * Aktivierung: config 'plugins.permission' => 'ZhlCertificate'.
 * Upgrade-sicher: eigenes Plugin, kein Core-Edit.
 */
class ZhlCertificate implements IPermissionService
{
    /** @var IPermissionService */
    private $base;

    public function __construct($base)
    {
        $this->base = $base;
    }

    public function CanAccessResource(IPermissibleResource $resource, UserSession $user)
    {
        return $this->base->CanAccessResource($resource, $user);
    }

    public function CanViewResource(IPermissibleResource $resource, UserSession $user)
    {
        return $this->base->CanViewResource($resource, $user);
    }

    public function CanBookResource(IPermissibleResource $resource, UserSession $user)
    {
        // Erst die native Gruppen-/User-Berechtigung (Stufe 1).
        if (!$this->base->CanBookResource($resource, $user)) {
            return false;
        }
        // Admins sind ausgenommen.
        if ($user->IsAdmin) {
            return true;
        }
        $resourceId = $resource->GetId();
        // Nur zertifikatspflichtige Geräte zusätzlich prüfen.
        if (!$this->requiresCertificate($resourceId)) {
            return true;
        }
        return $this->hasValidCertificate($user->UserId, $resourceId);
    }

    private function requiresCertificate($resourceId)
    {
        $cmd = new AdHocCommand('SELECT 1 AS found FROM zhl_certificate_required WHERE resource_id = @resourceId');
        $cmd->AddParameter(new Parameter('@resourceId', $resourceId));
        $reader = ServiceLocator::GetDatabase()->Query($cmd);
        $found = (bool)$reader->GetRow();
        $reader->Free();
        return $found;
    }

    private function hasValidCertificate($userId, $resourceId)
    {
        $cmd = new AdHocCommand(
            'SELECT 1 AS valid FROM zhl_certificate ' .
            'WHERE user_id = @userId AND resource_id = @resourceId ' .
            'AND (expires_at IS NULL OR expires_at > NOW())'
        );
        $cmd->AddParameter(new Parameter('@userId', $userId));
        $cmd->AddParameter(new Parameter('@resourceId', $resourceId));
        $reader = ServiceLocator::GetDatabase()->Query($cmd);
        $valid = (bool)$reader->GetRow();
        $reader->Free();
        return $valid;
    }
}
