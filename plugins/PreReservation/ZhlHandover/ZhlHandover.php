<?php
/**
 * ZHL Übergabe-Modul Phase A — PreReservation-Gate.
 *
 * Blockiert das Speichern einer Reservierung, solange ein übergabepflichtiges
 * Gerät (F8: Ressourcen-Custom-Attribut "handover_required") gebucht wird, ohne
 * dass eine bestätigte Übergabe (Abholung + Rückgabe) über terminplaner_ubt
 * terminiert wurde. Der Nutzer trägt das Übergabe-Token in das Reservierungs-
 * Custom-Attribut "handover_token" ein (befüllt durch Web/zhl-handover-select.php).
 *
 * PreReservation (nicht PostReservation), weil die Buchung VOR dem Speichern
 * geblockt werden muss — danach wäre sie schon angelegt.
 *
 * Aktivierung: config 'plugins.prereservation' => 'ZhlHandover'.
 * Upgrade-sicher: eigenes Plugin; einziger Core-Edit ist der choices-Whitelist-
 * Eintrag in lib/Config/ConfigKeys.php (5.1.0 validiert Plugin-Namen).
 */
class ZhlHandover implements IPreReservationFactory
{
    /** @var PreReservationFactory */
    private $factoryToDecorate;

    public function __construct(PreReservationFactory $factoryToDecorate)
    {
        $this->factoryToDecorate = $factoryToDecorate;
        require_once(dirname(__FILE__) . '/ZhlHandoverValidation.php');
    }

    public function CreatePreAddService(UserSession $userSession)
    {
        $base = $this->factoryToDecorate->CreatePreAddService($userSession);
        return new ZhlHandoverValidation($base);
    }

    public function CreatePreUpdateService(UserSession $userSession)
    {
        $base = $this->factoryToDecorate->CreatePreUpdateService($userSession);
        return new ZhlHandoverValidation($base);
    }

    public function CreatePreDeleteService(UserSession $userSession)
    {
        return $this->factoryToDecorate->CreatePreDeleteService($userSession);
    }

    public function CreatePreApprovalService(UserSession $userSession)
    {
        return $this->factoryToDecorate->CreatePreApprovalService($userSession);
    }

    public function CreatePreCheckinService(UserSession $userSession)
    {
        return $this->factoryToDecorate->CreatePreCheckinService($userSession);
    }

    public function CreatePreCheckoutService(UserSession $userSession)
    {
        return $this->factoryToDecorate->CreatePreCheckoutService($userSession);
    }
}
