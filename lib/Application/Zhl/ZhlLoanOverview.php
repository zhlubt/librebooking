<?php

/**
 * ZHL Ausleihe-/Rückgabe-Übersicht — Ausleihe-Manager-Perspektive (2026-07-15).
 *
 * Grundwahrheit sind die NATIVEN Reservierungen (`reservation_instances` über
 * `ReservationViewRepository`), NICHT `zhl_booking_handover`. Letztere Tabelle deckt nur
 * Buchungen ab, die über den ZHL-Self-Service-Flow (`zhl-book.php`/`zhl-bundle-book.php`)
 * MIT persönlicher Übergabe liefen. Reservierungen aus dem nativen Admin-Panel/Kalender
 * oder mit „Abholung am Ablageort"/„nicht nötig" erzeugen NIE eine handover-Zeile und
 * wären sonst in keiner Übersicht sichtbar — read-only verifiziert (2026-07-15): 0 von 14
 * echten Reservierungen im 30-Tage-Fenster hatten eine handover-Zeile.
 *
 * `zhl_booking_handover` ist hier nur noch ein OPTIONALER Zusatz-Layer (Termin für die
 * persönliche Übergabe, zuständige Person), nie ein Filter auf die Grundmenge.
 *
 * Zuordnung handover→Reservierung über `reference_number` — verifiziert eindeutig: der
 * ZHL-Buchungsflow erzwingt `RepeatType::None` (ZhlReservationFacade::GetRepeatType), und
 * `reference_number` hatte live 0 Mehrfachtreffer in `reservation_instances`.
 */
class ZhlLoanOverview
{
    public const EDGE_START = 'start'; // Ausleihen (Abholung)
    public const EDGE_END = 'end';     // Rückgaben

    /**
     * Lädt alle Reservierungen, deren Beginn ($edge=EDGE_START) bzw. Ende ($edge=EDGE_END)
     * in [$startLocal, $endLocal) fällt, angereichert mit Übergabe-Konfiguration je Gerät
     * und optionalem Handover-Termin. Bundle-Buchungen (mehrere Geräte je Serie) werden zu
     * EINER Zeile konsolidiert.
     *
     * @return array<int,array<string,mixed>> flach, chronologisch nach dem für $edge
     *   relevanten (ggf. durch den Handover-Termin präzisierten) Zeitpunkt sortiert
     */
    public static function Load(Date $startLocal, Date $endLocal, string $edge, PDO $pdo, string $tz): array
    {
        $repository = new ReservationViewRepository();
        // GetReservations() filtert auf ÜBERSCHNEIDUNG mit dem Fenster (Start ODER Ende ODER
        // Umspannung liegt im Fenster) — großzügiger als gewünscht. Danach in PHP exakt auf
        // die gewünschte Kante (Start bzw. Ende) nachfiltern.
        $reservations = $repository->GetReservations(
            $startLocal,
            $endLocal,
            ReservationViewRepository::ALL_USERS,
            ReservationUserLevel::ALL,
            ReservationViewRepository::ALL_SCHEDULES,
            ReservationViewRepository::ALL_RESOURCES,
            true
        );

        $filtered = [];
        foreach ($reservations as $r) {
            $edgeDate = $edge === self::EDGE_END ? $r->EndDate : $r->StartDate;
            if (!$edgeDate->LessThan($endLocal) || $edgeDate->LessThan($startLocal)) {
                continue;
            }
            $filtered[] = $r;
        }
        if (!$filtered) {
            return [];
        }

        $seriesIds = [];
        $refs = [];
        foreach ($filtered as $r) {
            $seriesIds[(int)$r->SeriesId] = true;
            $refs[(string)$r->ReferenceNumber] = true;
        }

        $resourcesBySeries = self::loadResourcesBySeries($pdo, array_keys($seriesIds));
        $handoverType = $edge === self::EDGE_END ? 'return' : 'pickup';
        $handoverByRef = self::loadHandover($pdo, array_keys($refs), $handoverType);

        $rows = [];
        foreach ($filtered as $r) {
            $rows[] = self::toRow($r, $edge, $resourcesBySeries, $handoverByRef, $tz);
        }
        usort($rows, fn($a, $b) => strcmp($a['sortKey'], $b['sortKey']));
        return $rows;
    }

    /**
     * Gruppiert einen sortierten Zeilen-Array nach lokalem Kalendertag (Feld `dayKey`).
     *
     * @param array<int,array<string,mixed>> $rows aus Load()
     * @return array<int,array{dayKey:string,weekday:string,dateLabel:string,tag:?string,items:array}>
     */
    public static function GroupByDay(array $rows, Date $todayLocal): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['dayKey']][] = $row;
        }
        ksort($groups);

        $weekdayNames = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
        $todayKey = $todayLocal->Format('Y-m-d');
        $tomorrowKey = $todayLocal->AddDays(1)->Format('Y-m-d');

        $out = [];
        foreach ($groups as $key => $items) {
            /** @var Date $first */
            $first = $items[0]['effectiveLocal'];
            $out[] = [
                'dayKey' => $key,
                'weekday' => $weekdayNames[(int)$first->Format('w')],
                'dateLabel' => $first->Format('d.m.Y'),
                'tag' => $key === $todayKey ? 'Heute' : ($key === $tomorrowKey ? 'Morgen' : null),
                'items' => $items,
            ];
        }
        return $out;
    }

    /** Ressourcen (+ zhl_uebergabe-Konfiguration) je series_id, batched über alle betroffenen Serien. */
    private static function loadResourcesBySeries(PDO $pdo, array $seriesIds): array
    {
        if (!$seriesIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($seriesIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT rr.series_id, r.resource_id, r.name,
                    u.abholung, u.abholort, u.rueckgabe, u.rueckgabeort, u.einfuehrung
             FROM reservation_resources rr
             JOIN resources r ON r.resource_id = rr.resource_id
             LEFT JOIN zhl_uebergabe u ON u.resource_id = r.resource_id
             WHERE rr.series_id IN ($in)
             ORDER BY r.name"
        );
        $stmt->execute($seriesIds);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int)$row['series_id']][] = $row;
        }
        return $out;
    }

    /**
     * ALLE zhl_booking_handover-Zeilen je reference_number für den gegebenen Typ, batched.
     * Eine Bundle-Buchung (mehrere Geräte, gleiche reference_number) kann MEHRERE Zeilen
     * haben (eine je Gerät, das persönliche Übergabe braucht) — Aufrufer dürfen daher nicht
     * einfach "die eine" Zeile nehmen, sondern müssen über alle aggregieren.
     *
     * @return array<string,array<int,array<string,mixed>>> reference_number => Zeilen
     */
    private static function loadHandover(PDO $pdo, array $refs, string $type): array
    {
        if (!$refs) {
            return [];
        }
        $in = implode(',', array_fill(0, count($refs), '?'));
        $stmt = $pdo->prepare(
            "SELECT * FROM zhl_booking_handover
             WHERE type = ? AND reference_number IN ($in)
             ORDER BY scheduled_start_utc IS NULL, scheduled_start_utc, id"
        );
        $stmt->execute(array_merge([$type], $refs));
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string)$row['reference_number']][] = $row;
        }
        return $out;
    }

    /** Rang für den "am wenigsten fortgeschrittenen" Status über mehrere handover-Zeilen. */
    private static function statusRank(?string $status): int
    {
        return match ($status) {
            'confirmed' => 2,
            'done' => 3,
            default => 1, // requested, cancelled, unbekannt, fehlend
        };
    }

    /** @param ReservationItemView $r */
    private static function toRow($r, string $edge, array $resourcesBySeries, array $handoverByRef, string $tz): array
    {
        $startLocal = $r->StartDate->ToTimezone($tz);
        $endLocal = $r->EndDate->ToTimezone($tz);
        $ref = (string)$r->ReferenceNumber;
        $seriesId = (int)($r->SeriesId ?? 0);

        $resources = $resourcesBySeries[$seriesId] ?? [];
        if (!$resources) {
            // Fallback, falls die Serie aus irgendeinem Grund nicht batched gefunden wurde.
            $resources = [[
                'resource_id' => $r->ResourceId, 'name' => $r->ResourceName,
                'abholung' => null, 'abholort' => null, 'rueckgabe' => null, 'rueckgabeort' => null,
                'einfuehrung' => null,
            ]];
        }
        $deviceNames = array_column($resources, 'name');
        $deviceCount = count($resources);

        $modeKey = $edge === self::EDGE_END ? 'rueckgabe' : 'abholung';
        $locKey = $edge === self::EDGE_END ? 'rueckgabeort' : 'abholort';
        $needsPersonal = false;
        $einfuehrungNoetig = false;
        $location = '';
        foreach ($resources as $res) {
            $mode = $res[$modeKey] ?? null;
            $applies = $edge === self::EDGE_END
                ? ($mode === 'abgeben_persoenlich')
                : !in_array($mode ?? 'abholen', ['ablageort', 'nicht_noetig'], true);
            if ($applies) {
                $needsPersonal = true;
                if ($location === '' && !empty($res[$locKey])) {
                    $location = (string)$res[$locKey];
                }
            }
            if (($res['einfuehrung'] ?? null) === 'notwendig') {
                $einfuehrungNoetig = true;
            }
        }
        if ($location === '') {
            foreach ($resources as $res) {
                if (!empty($res[$locKey])) {
                    $location = (string)$res[$locKey];
                    break;
                }
            }
        }

        $handoverRows = $needsPersonal ? ($handoverByRef[$ref] ?? []) : [];
        // Bei mehreren Zeilen (Bundle) die am wenigsten fortgeschrittene für Status/Anzeige
        // nehmen — "erledigt" darf ein noch offenes Gerät derselben Buchung nicht verdecken.
        $handover = null;
        foreach ($handoverRows as $row) {
            if ($handover === null || self::statusRank($row['status'] ?? null) < self::statusRank($handover['status'] ?? null)) {
                $handover = $row;
            }
        }
        [$statusKey, $statusLabel, $badgeClass] = self::deriveStatus($needsPersonal, $handover);

        $borrower = trim((string)($r->OwnerFirstName ?? '') . ' ' . (string)($r->OwnerLastName ?? ''));
        if ($borrower === '') {
            $borrower = (string)($r->OwnerEmailAddress ?? '');
        }

        $title = trim((string)$r->Title);
        if ($title === '') {
            $title = $deviceCount > 0 ? $deviceNames[0] : 'Buchung';
        }

        // Effektiver Zeitpunkt: der VEREINBARTE Übergabe-Termin ist relevanter als der reine
        // Reservierungsrand, wenn er existiert (z. B. Abholung 2 Tage vor Nutzungsbeginn).
        $effective = $edge === self::EDGE_END ? $endLocal : $startLocal;
        $scheduledField = $edge === self::EDGE_END ? 'scheduled_end_utc' : 'scheduled_start_utc';
        if ($handover && !empty($handover[$scheduledField])) {
            try {
                $effective = Date::Parse((string)$handover[$scheduledField], 'UTC')->ToTimezone($tz);
            } catch (Throwable $e) {
                // Fallback bleibt der Reservierungsrand.
            }
        }

        return [
            'ref' => $ref,
            'title' => $title,
            'kind' => $deviceCount > 1 ? 'bundle' : 'device',
            'deviceCount' => $deviceCount,
            'devices' => $deviceNames,
            'borrower' => $borrower,
            'needsPersonal' => $needsPersonal,
            'einfuehrungNoetig' => $einfuehrungNoetig,
            'location' => $location,
            'statusKey' => $statusKey,
            'statusLabel' => $statusLabel,
            'badgeClass' => $badgeClass,
            'staffMemberId' => isset($handover['staff_member_id']) ? (int)$handover['staff_member_id'] : null,
            'staffRole' => $handover['staff_role'] ?? null,
            'handoverId' => isset($handover['id']) ? (int)$handover['id'] : null,
            'handoverToken' => $handover['handover_token'] ?? null,
            'handoverResourceId' => isset($handover['resource_id']) ? (int)$handover['resource_id'] : null,
            'hasSingleHandoverRow' => count($handoverRows) === 1,
            'effectiveLocal' => $effective,
            'dayKey' => $effective->Format('Y-m-d'),
            'timeLabel' => $effective->Format('H:i'),
            'dateTimeLabel' => $effective->Format('d.m.Y, H:i'),
            'sortKey' => $effective->Format('Y-m-d H:i:s') . '|' . $ref,
            'startLocal' => $startLocal,
            'endLocal' => $endLocal,
        ];
    }

    /** @return array{0:string,1:string,2:string} [statusKey, statusLabel, badgeCssClass] */
    private static function deriveStatus(bool $needsPersonal, ?array $handover): array
    {
        if (!$needsPersonal) {
            return ['none', 'Kein Termin nötig', 'badge-muted'];
        }
        if (!$handover) {
            return ['missing', 'Termin fehlt', 'badge-warn'];
        }
        return match ($handover['status'] ?? '') {
            'requested' => ['requested', 'Angefragt', 'badge-info'],
            'confirmed' => ['confirmed', 'Terminiert', 'badge-ok'],
            'done' => ['done', 'Erledigt', 'badge-ok'],
            default => ['missing', 'Termin fehlt', 'badge-warn'], // cancelled/unbekannt -> braucht neuen Termin
        };
    }
}
