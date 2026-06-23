<?php
/**
 * Validierung für das ZHL-Übergabe-Gate (siehe ZhlHandover.php).
 *
 * Greift NUR, wenn mindestens eine Ressource der Reservierung übergabepflichtig
 * ist. Dann muss zum eingetragenen handover_token eine bestätigte Abholung UND
 * Rückgabe in zhl_booking_handover vorliegen, sonst wird das Speichern geblockt.
 */
class ZhlHandoverValidation implements IReservationValidationService
{
    // CustomAttributeCategory: RESERVATION = 1, RESOURCE = 4 (LibreBooking-Konstanten).
    private const CATEGORY_RESERVATION = 1;
    private const CATEGORY_RESOURCE = 4;

    // Konventions-Labels der Custom-Attribute (vom ZHL-Admin so anzulegen).
    private const LABEL_REQUIRED = 'handover_required';
    private const LABEL_TOKEN = 'handover_token';

    /** @var IReservationValidationService */
    private $serviceToDecorate;

    public function __construct(IReservationValidationService $serviceToDecorate)
    {
        $this->serviceToDecorate = $serviceToDecorate;
    }

    public function Validate($series, $retryParameters = null)
    {
        $result = $this->serviceToDecorate->Validate($series, $retryParameters);

        // Andere Regeln zuerst — bei deren Fehlschlag nicht zusätzlich prüfen.
        if (!$result->CanBeSaved()) {
            return $result;
        }

        return $this->EvaluateHandoverRule($series);
    }

    private function EvaluateHandoverRule($series)
    {
        // 1. Welche der gebuchten Ressourcen sind übergabepflichtig?
        $requiredAttributeId = $this->resolveAttributeId(self::LABEL_REQUIRED, self::CATEGORY_RESOURCE);
        if ($requiredAttributeId === null) {
            // Attribut nicht angelegt → Modul faktisch inaktiv, kein Block.
            return new ReservationValidationResult();
        }

        $needsHandover = false;
        foreach ($series->AllResources() as $resource) {
            if ($this->resourceRequiresHandover($resource->GetId(), $requiredAttributeId)) {
                $needsHandover = true;
                break;
            }
        }

        if (!$needsHandover) {
            return new ReservationValidationResult();
        }

        // 2. Token aus dem Reservierungs-Attribut lesen.
        $tokenAttributeId = $this->resolveAttributeId(self::LABEL_TOKEN, self::CATEGORY_RESERVATION);
        $token = $tokenAttributeId !== null ? trim((string)$series->GetAttributeValue($tokenAttributeId)) : '';

        $message = 'Dieses Gerät wird persönlich übergeben. Bitte zuerst einen Abhol- UND '
            . 'Rückgabe-Termin über den Übergabe-Assistenten wählen und das Übergabe-Token '
            . 'eintragen.';

        // Fehler als string[] (Vertrag des Konstruktors; Template iteriert {foreach from=$Errors}).
        if ($token === '' || !preg_match('/^[A-Za-z0-9]{8,64}$/', $token)) {
            return new ReservationValidationResult(false, [$message]);
        }

        // 3. Token muss dem buchenden User gehören (Auth-Bindung, Codex-Finding, strikt):
        //    fremde UND eigentümerlose Token werden blockiert. Legitime Token werden vom
        //    Assistenten IMMER vor der Anzeige beansprucht → ein gültiger Vorgang hat stets
        //    einen Eigentümer. Damit ist auch ein nur-via-notify befülltes Token ohne
        //    Eigentümer-Datensatz nicht ausnutzbar.
        if ($this->tokenOwner($token) !== (int)$series->UserId()) {
            return new ReservationValidationResult(false, [$message]);
        }

        // 4. Bestätigte Abholung UND Rückgabe zum Token vorhanden?
        $types = $this->confirmedHandoverTypes($token);
        if (in_array('pickup', $types, true) && in_array('return', $types, true)) {
            return new ReservationValidationResult();
        }

        return new ReservationValidationResult(false, [$message]);
    }

    /**
     * @return int|null custom_attribute_id für ein Label/Kategorie, oder null.
     */
    private function resolveAttributeId($label, $category)
    {
        $cmd = new AdHocCommand(
            'SELECT custom_attribute_id FROM custom_attributes ' .
            'WHERE display_label = @label AND attribute_category = @category LIMIT 1'
        );
        $cmd->AddParameter(new Parameter('@label', $label));
        $cmd->AddParameter(new Parameter('@category', $category));
        $reader = ServiceLocator::GetDatabase()->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        return $row ? (int)$row['custom_attribute_id'] : null;
    }

    private function resourceRequiresHandover($resourceId, $requiredAttributeId)
    {
        $cmd = new AdHocCommand(
            'SELECT attribute_value FROM custom_attribute_values ' .
            'WHERE entity_id = @entityId AND custom_attribute_id = @attrId ' .
            'AND attribute_category = @category LIMIT 1'
        );
        $cmd->AddParameter(new Parameter('@entityId', $resourceId));
        $cmd->AddParameter(new Parameter('@attrId', $requiredAttributeId));
        $cmd->AddParameter(new Parameter('@category', self::CATEGORY_RESOURCE));
        $reader = ServiceLocator::GetDatabase()->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        if (!$row) {
            return false;
        }
        $value = strtolower(trim((string)$row['attribute_value']));
        return in_array($value, ['1', 'true', 'yes', 'ja', 'on'], true);
    }

    /**
     * @return int|null User-ID, der das Token erzeugt hat, oder null (kein Eigentümer-Datensatz).
     */
    private function tokenOwner($token)
    {
        $cmd = new AdHocCommand('SELECT user_id FROM zhl_handover_token WHERE handover_token = @token');
        $cmd->AddParameter(new Parameter('@token', $token));
        $reader = ServiceLocator::GetDatabase()->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        return $row ? (int)$row['user_id'] : null;
    }

    /**
     * @return string[] bestätigte Übergabe-Typen ('pickup'/'return') zum Token.
     */
    private function confirmedHandoverTypes($token)
    {
        $cmd = new AdHocCommand(
            "SELECT type FROM zhl_booking_handover " .
            "WHERE handover_token = @token AND status IN ('confirmed','done')"
        );
        $cmd->AddParameter(new Parameter('@token', $token));
        $reader = ServiceLocator::GetDatabase()->Query($cmd);
        $types = [];
        while ($row = $reader->GetRow()) {
            $types[] = $row['type'];
        }
        $reader->Free();
        return $types;
    }
}
