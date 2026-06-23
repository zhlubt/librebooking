<?php
/**
 * Trägt nach dem Speichern die Reservierungs-Referenz in die Übergabe-Datensätze nach.
 * Siehe ZhlHandoverLink.php.
 */
class ZhlHandoverLinkNotification implements IReservationNotificationService
{
    private const CATEGORY_RESERVATION = 1;
    private const LABEL_TOKEN = 'handover_token';

    /** @var IReservationNotificationService */
    private $base;

    public function __construct(IReservationNotificationService $base)
    {
        $this->base = $base;
    }

    public function Notify($reservationSeries)
    {
        try {
            $this->LinkHandover($reservationSeries);
        } catch (Exception $ex) {
            Log::Error('ZhlHandoverLink: %s', $ex->getMessage());
        }
        // Hauptablauf weiterlaufen lassen (Standard-Benachrichtigungen etc.).
        $this->base->Notify($reservationSeries);
    }

    private function LinkHandover($series)
    {
        $tokenAttributeId = $this->resolveTokenAttributeId();
        if ($tokenAttributeId === null) {
            return; // Attribut nicht angelegt -> Modul inaktiv
        }

        $token = trim((string)$series->GetAttributeValue($tokenAttributeId));
        if ($token === '' || !preg_match('/^[A-Za-z0-9]{8,64}$/', $token)) {
            return;
        }

        $instance = $series->CurrentInstance();
        $reference = $instance ? $instance->ReferenceNumber() : null;
        $instanceId = $instance ? $instance->ReservationId() : null;
        $seriesId = $series->SeriesId();

        // Referenz in die token-gebundenen Übergabe-Zeilen nachtragen.
        $update = new AdHocCommand(
            'UPDATE zhl_booking_handover ' .
            'SET reference_number = @ref, series_id = @sid, reservation_instance_id = @iid, ' .
            'updated_at = @now WHERE handover_token = @token'
        );
        $update->AddParameter(new Parameter('@ref', $reference));
        $update->AddParameter(new Parameter('@sid', $seriesId));
        $update->AddParameter(new Parameter('@iid', $instanceId));
        $update->AddParameter(new Parameter('@now', Date::Now()->ToDatabase()));
        $update->AddParameter(new Parameter('@token', $token));
        ServiceLocator::GetDatabase()->Execute($update);

        // Auch im Token-Ownership-Datensatz die Referenz festhalten.
        $updateToken = new AdHocCommand(
            'UPDATE zhl_handover_token SET reference_number = @ref WHERE handover_token = @token'
        );
        $updateToken->AddParameter(new Parameter('@ref', $reference));
        $updateToken->AddParameter(new Parameter('@token', $token));
        ServiceLocator::GetDatabase()->Execute($updateToken);
    }

    private function resolveTokenAttributeId()
    {
        $cmd = new AdHocCommand(
            'SELECT custom_attribute_id FROM custom_attributes ' .
            'WHERE display_label = @label AND attribute_category = @category LIMIT 1'
        );
        $cmd->AddParameter(new Parameter('@label', self::LABEL_TOKEN));
        $cmd->AddParameter(new Parameter('@category', self::CATEGORY_RESERVATION));
        $reader = ServiceLocator::GetDatabase()->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        return $row ? (int)$row['custom_attribute_id'] : null;
    }
}
