<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Database/namespace.php');
require_once(ROOT_DIR . 'Domain/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlLoanOverview.php');
require_once(ROOT_DIR . 'Web/zhl-handover-lib.php');
require_once(ROOT_DIR . 'Web/zhl-return-lib.php');

/**
 * Presenter „Rückgaben" — Agenda für den Ausleihe-Manager: was kommt in den nächsten
 * 7/14/30 Tagen zurück, von wem, wohin, und was ist bereits überfällig. Datenquelle ist
 * [[ZhlLoanOverview]] (native Reservierungen, NICHT nur handover-Zeilen). Innerhalb eines
 * Tages zusätzlich nach Rückgabeort gruppiert (Gegenstück zur alten Tagesansicht).
 */
class ZhlRueckgabenPresenter
{
    private const OVERDUE_LOOKBACK_DAYS = 30;

    /** @var IZhlRueckgabenPage */
    private $page;

    public function __construct(IZhlRueckgabenPage $page)
    {
        $this->page = $page;
    }

    public function PageLoad(UserSession $user, int $days): void
    {
        $tz = $user->Timezone;
        $todayLocal = Date::Now()->ToTimezone($tz);
        $todayLocal = Date::Parse($todayLocal->Format('Y-m-d') . ' 00:00:00', $tz);
        $windowStartLocal = $todayLocal->AddDays(-self::OVERDUE_LOOKBACK_DAYS);
        $windowEndLocal = $todayLocal->AddDays($days);

        $pdo = zhl_handover_db();
        $rows = ZhlLoanOverview::Load($windowStartLocal, $windowEndLocal, ZhlLoanOverview::EDGE_END, $pdo, $tz);

        $staffNames = [];
        foreach ($rows as $r) {
            if ($r['needsPersonal'] && $r['staffMemberId']) {
                $staffNames = zhl_handover_staff_names(zhl_handover_type_labels());
                break;
            }
        }

        $overdue = [];
        $upcoming = [];
        foreach ($rows as &$r) {
            $r['devicesLabel'] = implode(', ', $r['devices']);
            if ($r['staffMemberId'] && isset($staffNames[$r['staffMemberId']])) {
                $r['staffName'] = $staffNames[$r['staffMemberId']]['name'];
                $r['staffRoleLabel'] = $staffNames[$r['staffMemberId']]['role'] === 'primary' ? 'Hilfskraft' : 'Team';
            } else {
                $r['staffName'] = null;
                $r['staffRoleLabel'] = $r['staffRole'] === 'primary' ? 'Hilfskraft' : ($r['staffRole'] === 'backup' ? 'Team' : null);
            }

            $self = $r['handoverId'] ? zhl_return_self_for_handover($r['handoverId']) : null;
            $r['selfReturn'] = $self !== null;
            $r['selfReturnLocation'] = $self['location_label'] ?? '';
            $r['selfReturnPhotoId'] = $self['id'] ?? null;

            $isOverdue = $r['needsPersonal'] && $r['statusKey'] !== 'done' && $r['effectiveLocal']->LessThan($todayLocal);
            if ($isOverdue) {
                $overdue[] = $r;
            } elseif (!$r['effectiveLocal']->LessThan($todayLocal)) {
                $upcoming[] = $r;
            }
            // Weder überfällig noch im Vorschau-Fenster (z. B. reines Lookback-Rauschen) -> weglassen.
        }
        unset($r);

        $groups = ZhlLoanOverview::GroupByDay($upcoming, $todayLocal);
        foreach ($groups as &$day) {
            $day['byLocation'] = $this->groupByLocation($day['items']);
        }
        unset($day);

        $this->page->BindRueckgaben([
            'days' => $days,
            'total' => count($upcoming),
            'overdueTotal' => count($overdue),
            'overdue' => $overdue,
            'rangeLabel' => $todayLocal->Format('d.m.') . '–' . $windowEndLocal->AddDays(-1)->Format('d.m.Y'),
            'groups' => $groups,
        ]);
    }

    /** Zeilen eines Tages nach Rückgabeort gruppieren (leerer Ort -> Sammelgruppe ans Ende). */
    private function groupByLocation(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $key = $item['location'] !== '' ? $item['location'] : '__none__';
            $groups[$key][] = $item;
        }
        $none = $groups['__none__'] ?? null;
        unset($groups['__none__']);
        ksort($groups);
        if ($none !== null) {
            $groups['__none__'] = $none;
        }
        $out = [];
        foreach ($groups as $key => $items) {
            $out[] = ['label' => $key === '__none__' ? 'Ohne festen Rückgabeort' : $key, 'items' => $items];
        }
        return $out;
    }
}

interface IZhlRueckgabenPage
{
    public function BindRueckgaben(array $vm);
}
