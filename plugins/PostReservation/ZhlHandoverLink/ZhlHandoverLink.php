<?php
/**
 * ZHL Übergabe-Modul Phase A — PostReservation-Verknüpfung.
 *
 * Nachdem eine Reservierung gespeichert wurde, trägt dieses Plugin die jetzt
 * bekannte Reservierung (reference_number, series_id, reservation_instance_id) in
 * die zuvor token-gebundenen Übergabe-Datensätze (zhl_booking_handover) nach. Damit
 * wird die Übergabe sichtbar einer konkreten Reservierung zugeordnet (vorher nur über
 * das handover_token verbunden — Henne-Ei-Auflösung).
 *
 * Aktivierung: config 'plugins.postreservation' => 'ZhlHandoverLink'.
 * Eigener Klassenname (≠ PreReservation-Plugin ZhlHandover), da beide gleichzeitig laden.
 * Schreibt fehlertolerant (try/catch) — ein Fehler hier darf die Buchung nie brechen.
 */
class ZhlHandoverLink implements IPostReservationFactory
{
    /** @var PostReservationFactory */
    private $factoryToDecorate;

    public function __construct(PostReservationFactory $factoryToDecorate)
    {
        $this->factoryToDecorate = $factoryToDecorate;
        require_once(dirname(__FILE__) . '/ZhlHandoverLinkNotification.php');
    }

    public function CreatePostAddService(UserSession $userSession)
    {
        return new ZhlHandoverLinkNotification($this->factoryToDecorate->CreatePostAddService($userSession));
    }

    public function CreatePostUpdateService(UserSession $userSession)
    {
        return new ZhlHandoverLinkNotification($this->factoryToDecorate->CreatePostUpdateService($userSession));
    }

    public function CreatePostDeleteService(UserSession $userSession)
    {
        return $this->factoryToDecorate->CreatePostDeleteService($userSession);
    }

    public function CreatePostApproveService(UserSession $userSession)
    {
        return new ZhlHandoverLinkNotification($this->factoryToDecorate->CreatePostApproveService($userSession));
    }

    public function CreatePostCheckinService(UserSession $userSession)
    {
        return $this->factoryToDecorate->CreatePostCheckinService($userSession);
    }

    public function CreatePostCheckoutService(UserSession $userSession)
    {
        return $this->factoryToDecorate->CreatePostCheckoutService($userSession);
    }
}
