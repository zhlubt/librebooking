<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/ZhlUebergabenPresenter.php');

/**
 * ZHL „Persönliche Übergaben" (vormals „Alle Übergaben", URL bleibt zhl-handover-admin.php
 * — bestehende Cross-Links aus Ausleihen/Rückgaben/QR-Seiten zeigen dorthin). Verwaltet den
 * Koordinationsprozess für Termine mit persönlicher Übergabe, mit Filtern. Nur fürs ZHL-Team.
 */
class ZhlUebergabenPage extends SecurePage implements IZhlUebergabenPage
{
    /** @var ZhlUebergabenPresenter */
    private $presenter;

    public function __construct()
    {
        parent::__construct('Persönliche Übergaben');
        $this->presenter = new ZhlUebergabenPresenter($this);
    }

    public function PageLoad()
    {
        $user = ServiceLocator::GetServer()->GetUserSession();
        if (!($user->IsAdmin || $user->IsResourceAdmin || $user->IsScheduleAdmin || $user->IsGroupAdmin)) {
            http_response_code(403);
            echo 'Nur für das ZHL-Team (Admin).';
            return;
        }

        $status = (string)$this->GetQuerystring('status');
        $type = (string)$this->GetQuerystring('type');
        $ref = trim((string)$this->GetQuerystring('ref'));
        $staff = trim((string)$this->GetQuerystring('staff'));
        $upcoming = $this->GetQuerystring('upcoming') !== '0'; // Default AN, explizit "0" schaltet aus

        $filters = [
            'status' => in_array($status, ['requested', 'confirmed', 'done', 'cancelled'], true) ? $status : '',
            'type' => in_array($type, ['pickup', 'return', 'einf'], true) ? $type : '',
            'ref' => $ref,
            'staff' => $staff,
            'upcoming' => $upcoming,
        ];

        $this->presenter->PageLoad($user, $filters);
        $this->Set('HideNavBar', false);
        $this->Display('zhl-uebergaben.tpl');
    }

    public function BindUebergaben(array $vm)
    {
        foreach ($vm as $key => $value) {
            $this->Set(ucfirst($key), $value);
        }
    }
}
