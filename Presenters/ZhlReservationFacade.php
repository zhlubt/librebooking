<?php

require_once(ROOT_DIR . 'Domain/namespace.php');
require_once(ROOT_DIR . 'Pages/Ajax/ReservationSavePage.php');

/**
 * Schlanke Facade, die `IReservationSavePage` (inkl. IReservationSaveResultsView + IRepeatOptionsComposite)
 * für eine EINZELNE, nicht wiederkehrende ZHL-Buchung erfüllt. Wird der nativen
 * ReservationPresenterFactory übergeben → ReservationSavePresenter::BuildReservation()/HandleReservation()
 * → nativer ReservationHandler. Dadurch bleibt die komplette native Validierung (Rechte, Konflikte,
 * Vorlauf, Blackouts, MaxConcurrent) die letzte Instanz; diese Klasse hält nur Eingabe + Ergebnis.
 *
 * Alle Methoden sind vollständig implementiert (Codex-Review 2026-06-24): fehlende Getter würden
 * BuildReservation/HandleReservation zur Laufzeit crashen. Defaults = „nichts": keine Wiederholung,
 * keine Reminder, keine Teilnehmer/Gäste/Zubehör/Attribute/Anhänge.
 */
class ZhlReservationFacade implements IReservationSavePage
{
    private $userId;
    private $resourceId;
    private $title;
    private $description;
    private $beginDate;   // 'Y-m-d'
    private $endDate;     // 'Y-m-d'
    private $beginTime;   // 'H:i'
    private $endTime;     // 'H:i'
    /** @var object[] je Element ->Id (int) und ->Value (string) — Reservierungs-Custom-Attribute */
    private $attributeValues = [];

    // Ergebnis (von Handler/Presenter zurückgeschrieben)
    private $saved = false;
    private $errors = [];
    private $warnings = [];
    private $referenceNumber = '';
    private $requiresApproval = false;

    public function __construct($userId, $resourceId, $title, $description, $beginDate, $beginTime, $endDate, $endTime, array $attributeValues = [])
    {
        $this->userId = (int)$userId;
        $this->resourceId = (int)$resourceId;
        $this->title = (string)$title;
        $this->description = (string)$description;
        $this->beginDate = (string)$beginDate;
        $this->beginTime = (string)$beginTime;
        $this->endDate = (string)$endDate;
        $this->endTime = (string)$endTime;
        $this->attributeValues = $attributeValues;
    }

    // --- Eingabe (IReservationSavePage) ---
    public function GetUserId()
    {
        return $this->userId;
    }
    public function GetResourceId()
    {
        return $this->resourceId;
    }
    public function GetTitle()
    {
        return $this->title;
    }
    public function GetDescription()
    {
        return $this->description;
    }
    public function GetStartDate()
    {
        return $this->beginDate;
    }
    public function GetEndDate()
    {
        return $this->endDate;
    }
    public function GetStartTime()
    {
        return $this->beginTime;
    }
    public function GetEndTime()
    {
        return $this->endTime;
    }
    public function GetResources()
    {
        return [];
    }
    public function GetParticipants()
    {
        return [];
    }
    public function GetInvitees()
    {
        return [];
    }
    public function GetAccessories()
    {
        return [];
    }
    public function GetAttributes()
    {
        return $this->attributeValues;
    }
    public function GetAttachments()
    {
        return [];
    }
    public function HasStartReminder()
    {
        return false;
    }
    public function GetStartReminderValue()
    {
        return '';
    }
    public function GetStartReminderInterval()
    {
        return '';
    }
    public function HasEndReminder()
    {
        return false;
    }
    public function GetEndReminderValue()
    {
        return '';
    }
    public function GetEndReminderInterval()
    {
        return '';
    }
    public function GetAllowParticipation()
    {
        return false;
    }
    public function GetParticipatingGuests()
    {
        return [];
    }
    public function GetInvitedGuests()
    {
        return [];
    }
    public function GetTermsOfServiceAcknowledgement()
    {
        return true;
    }

    // --- Wiederholung (IRepeatOptionsComposite) — keine Wiederholung ---
    public function GetRepeatType()
    {
        return RepeatType::None;
    }
    public function GetRepeatInterval()
    {
        return '';
    }
    public function GetRepeatWeekdays()
    {
        return [];
    }
    public function GetRepeatMonthlyType()
    {
        return '';
    }
    public function GetRepeatTerminationDate()
    {
        return $this->endDate; // parsebarer String; bei 'none' fachlich ignoriert
    }
    public function GetRepeatCustomDates()
    {
        return [];
    }

    // --- Ergebnis (IReservationSaveResultsView) ---
    public function SetReferenceNumber($referenceNumber)
    {
        $this->referenceNumber = $referenceNumber;
    }
    public function SetRequiresApproval($requiresApproval)
    {
        $this->requiresApproval = (bool)$requiresApproval;
    }
    public function SetSaveSuccessfulMessage($succeeded)
    {
        $this->saved = (bool)$succeeded;
    }
    public function SetErrors($errors)
    {
        $this->errors = is_array($errors) ? $errors : [$errors];
    }
    public function SetWarnings($warnings)
    {
        $this->warnings = is_array($warnings) ? $warnings : [$warnings];
    }
    public function SetRetryMessages($messages)
    {
    }
    public function SetCanBeRetried($canBeRetried)
    {
    }
    public function SetRetryParameters($retryParameters)
    {
    }
    public function GetRetryParameters()
    {
        return [];
    }
    public function SetCanJoinWaitList($canJoinWaitlist)
    {
    }

    // --- ZHL-Ergebnis-Accessoren ---
    public function WasSaved()
    {
        return $this->saved;
    }
    public function GetErrors()
    {
        return $this->errors;
    }
    public function ReferenceNumber()
    {
        return $this->referenceNumber;
    }
    public function RequiresApproval()
    {
        return $this->requiresApproval;
    }
}
