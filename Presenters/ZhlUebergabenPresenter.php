<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'Web/zhl-handover-lib.php');

/**
 * Presenter „Persönliche Übergaben" (vormals „Alle Übergaben"). Verwaltet NUR den
 * Koordinationsprozess für Termine mit persönlicher Übergabe (zhl_booking_handover) —
 * NICHT alle Ausleihen/Rückgaben, dafür siehe [[ZhlLoanOverview]] / Ausleihen / Rückgaben.
 * Filter: Status, Typ (Abholung/Rückgabe/Einführung), Referenznummer, „nur offene".
 */
class ZhlUebergabenPresenter
{
    private const STATUS_LABELS = [
        'requested' => 'Angefragt', 'confirmed' => 'Terminiert', 'done' => 'Erledigt', 'cancelled' => 'Storniert',
    ];
    private const STATUS_BADGES = [
        'requested' => 'badge-info', 'confirmed' => 'badge-ok', 'done' => 'badge-ok', 'cancelled' => 'badge-muted',
    ];
    private const TYPE_LABELS = ['pickup' => 'Abholung', 'return' => 'Rückgabe', 'einf' => 'Einführung'];

    /** @var IZhlUebergabenPage */
    private $page;

    public function __construct(IZhlUebergabenPage $page)
    {
        $this->page = $page;
    }

    public function PageLoad(UserSession $user, array $filters): void
    {
        $tz = $user->Timezone;

        $rows = zhl_handover_list(
            $filters['status'] ?: null,
            $filters['type'] ?: null,
            $filters['ref'] ?: null,
            !empty($filters['upcoming'])
        );

        $staffNames = [];
        foreach ($rows as $r) {
            if (!empty($r['staff_member_id'])) {
                $staffNames = zhl_handover_staff_names(zhl_handover_type_labels());
                break;
            }
        }

        $vmRows = [];
        foreach ($rows as $r) {
            $staffId = isset($r['staff_member_id']) ? (int)$r['staff_member_id'] : 0;
            $staff = $staffId > 0 ? ($staffNames[$staffId] ?? null) : null;
            $staffName = $staff['name'] ?? null;
            $roleKey = $staff['role'] ?? ($r['staff_role'] ?? null);
            $staffLabel = $staffName ?? ($roleKey === 'primary' ? 'Hilfskraft' : ($roleKey === 'backup' ? 'Team' : null));

            if ($filters['staff'] !== '' && (string)$staffId !== $filters['staff']) {
                continue;
            }

            $ref = (string)($r['reference_number'] ?? '');
            $token = (string)($r['handover_token'] ?? '');
            $borrower = zhl_handover_borrower_name($ref !== '' ? $ref : null, $token !== '' ? $token : null);

            $scheduledLocal = '';
            if (!empty($r['scheduled_start_utc'])) {
                try {
                    $scheduledLocal = Date::Parse((string)$r['scheduled_start_utc'], 'UTC')->ToTimezone($tz)->Format('d.m.Y, H:i');
                } catch (Throwable $e) {
                    $scheduledLocal = (string)$r['scheduled_start_utc'];
                }
            }

            $status = (string)$r['status'];
            $vmRows[] = [
                'id' => (int)$r['id'],
                'type' => (string)$r['type'],
                'typeLabel' => self::TYPE_LABELS[$r['type']] ?? $r['type'],
                'resourceName' => (string)($r['resource_name'] ?? '—'),
                'resourceId' => (int)($r['resource_id'] ?? 0),
                'ref' => $ref,
                'token' => $token,
                'scheduledLocal' => $scheduledLocal !== '' ? $scheduledLocal : '—',
                'borrower' => $borrower !== '' ? $borrower : null,
                'staffLabel' => $staffLabel,
                'staffId' => $staffId ?: null,
                'status' => $status,
                'statusLabel' => self::STATUS_LABELS[$status] ?? $status,
                'badgeClass' => self::STATUS_BADGES[$status] ?? 'badge-muted',
                'hasCheck' => (bool)$r['has_check'],
            ];
        }

        $staffOptions = [];
        foreach ($staffNames as $id => $s) {
            $staffOptions[] = ['id' => $id, 'label' => $s['name'] ?? ('Mitglied ' . $id)];
        }

        $this->page->BindUebergaben([
            'rows' => $vmRows,
            'total' => count($vmRows),
            'filters' => $filters,
            'staffOptions' => $staffOptions,
        ]);
    }
}

interface IZhlUebergabenPage
{
    public function BindUebergaben(array $vm);
}
