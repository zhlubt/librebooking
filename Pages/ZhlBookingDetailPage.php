<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/ZhlBookingDetailPresenter.php');

/**
 * ZHL Ausleih-Detail — eine einzelne eigene Buchung (read-only) im grünen Chrome.
 * SecurePage (Login erzwungen). Lädt nur Reservierungen, die dem angemeldeten
 * Nutzer gehören (OWNER-Sicht); fremde Referenznummern führen zur Übersicht.
 */
class ZhlBookingDetailPage extends SecurePage implements IZhlBookingDetailPage
{
    /** @var ZhlBookingDetailPresenter */
    private $presenter;

    public function __construct()
    {
        parent::__construct('Ausleihe');
        $this->presenter = new ZhlBookingDetailPresenter($this);
    }

    public function PageLoad()
    {
        $user = ServiceLocator::GetServer()->GetUserSession();
        if ($this->IsPost()) {
            $this->EnforceCSRFCheck();
            $this->presenter->HandleCancel($user);
            return;
        }
        $this->presenter->PageLoad($user);
    }

    public function BindDetail(array $vm)
    {
        foreach ($vm as $key => $value) {
            $this->Set(ucfirst($key), $value);
        }
        $this->Set('HideNavBar', false);
        $this->Display('zhl-booking-detail.tpl');
    }

    public function RedirectToBookings()
    {
        $this->Redirect('zhl-bookings.php');
    }

    public function RedirectAfterCancel($warning = '')
    {
        $q = 'zhl-bookings.php?storniert=1';
        if ($warning !== '') {
            $q .= '&warn=' . urlencode($warning);
        }
        $this->Redirect($q);
    }
}

interface IZhlBookingDetailPage
{
    public function BindDetail(array $vm);
    public function RedirectToBookings();
    public function RedirectAfterCancel($warning = '');
}
