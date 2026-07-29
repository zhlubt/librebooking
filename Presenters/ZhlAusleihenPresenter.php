<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Database/namespace.php');
require_once(ROOT_DIR . 'Domain/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlLoanOverview.php');
require_once(ROOT_DIR . 'Web/zhl-handover-lib.php');

/**
 * Presenter „Ausleihen" — Agenda für den Ausleihe-Manager: was geht in den nächsten
 * 7/14/30 Tagen raus, an wen, und wer ist für die persönliche Übergabe zuständig.
 * Datenquelle ist [[ZhlLoanOverview]] (native Reservierungen, NICHT nur handover-Zeilen).
 */
class ZhlAusleihenPresenter
{
    /** @var IZhlAusleihenPage */
    private $page;

    public function __construct(IZhlAusleihenPage $page)
    {
        $this->page = $page;
    }

    public function PageLoad(UserSession $user, int $days, string $filter = 'upcoming'): void
    {
        $tz = $user->Timezone;
        $todayLocal = Date::Now()->ToTimezone($tz);
        $todayLocal = Date::Parse($todayLocal->Format('Y-m-d') . ' 00:00:00', $tz);

        $pdo = zhl_handover_db();

        if ($filter === 'this_week') {
            // Diese Woche: Montag bis Sonntag
            $daysToMonday = (int)$todayLocal->Format('N') - 1;
            $mondayLocal = $todayLocal->AddDays(-$daysToMonday);
            $sundayLocal = $mondayLocal->AddDays(6)->AddHours(23)->AddMinutes(59)->AddSeconds(59);
            $rows = ZhlLoanOverview::Load($mondayLocal, $sundayLocal, ZhlLoanOverview::EDGE_START, $pdo, $tz);
            $rangeLabel = $mondayLocal->Format('d.m.') . '–' . $sundayLocal->Format('d.m.Y');
        } elseif ($filter === 'active') {
            // Derzeit ausgeliehen: gestartet in der Vergangenheit, noch nicht beendet
            $startBackLocal = $todayLocal->AddDays(-180);
            $endLocal = $todayLocal->AddDays(1);
            $allRows = ZhlLoanOverview::Load($startBackLocal, $endLocal, ZhlLoanOverview::EDGE_START, $pdo, $tz);
            // Filter: Nur behalten, die heute noch gültig sind (gestartet <= heute, ende > heute)
            $rows = [];
            foreach ($allRows as $r) {
                if (isset($r['endLocal']) && $r['endLocal'] > $todayLocal) {
                    $rows[] = $r;
                }
            }
            $rangeLabel = 'Derzeit ausgeliehen';
        } else {
            // upcoming (default)
            $windowEndLocal = $todayLocal->AddDays($days);
            $rows = ZhlLoanOverview::Load($todayLocal, $windowEndLocal, ZhlLoanOverview::EDGE_START, $pdo, $tz);
            $rangeLabel = $todayLocal->Format('d.m.') . '–' . $windowEndLocal->AddDays(-1)->Format('d.m.Y');
        }

        $staffNames = [];
        if ($rows) {
            $needsStaff = false;
            foreach ($rows as $r) {
                if ($r['needsPersonal'] && $r['staffMemberId']) {
                    $needsStaff = true;
                    break;
                }
            }
            if ($needsStaff) {
                $staffNames = zhl_handover_staff_names(zhl_handover_type_labels());
            }
        }
        foreach ($rows as &$r) {
            $r['devicesLabel'] = implode(', ', $r['devices']);
            if ($r['staffMemberId'] && isset($staffNames[$r['staffMemberId']])) {
                $r['staffName'] = $staffNames[$r['staffMemberId']]['name'];
                $r['staffRoleLabel'] = $staffNames[$r['staffMemberId']]['role'] === 'primary' ? 'Hilfskraft' : 'Team';
            } else {
                $r['staffName'] = null;
                $r['staffRoleLabel'] = $r['staffRole'] === 'primary' ? 'Hilfskraft' : ($r['staffRole'] === 'backup' ? 'Team' : null);
            }
        }
        unset($r);

        $days_grouped = ZhlLoanOverview::GroupByDay($rows, $todayLocal);

        $this->page->BindAusleihen([
            'days' => $days,
            'filter' => $filter,
            'total' => count($rows),
            'rangeLabel' => $rangeLabel,
            'groups' => $days_grouped,
        ]);
    }
}

interface IZhlAusleihenPage
{
    public function BindAusleihen(array $vm);
}
