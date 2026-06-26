<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Database/namespace.php');
require_once(ROOT_DIR . 'Domain/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Schedule/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Attributes/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlAvailabilityService.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlBundleService.php');

/**
 * Presenter der ZHL-Dashboard-Startseite (verfügbarkeit-first Raster).
 * Read-only: liest validierte GET-Parameter, baut das Raster über den ZHL-Verfügbarkeits-
 * Service und reicht ein View-Model an die Page. Buchen läuft danach über die native
 * Reservierungsseite (reservation.php).
 */
class ZhlDashboardPresenter
{
    /** @var IZhlDashboardPage */
    private $page;

    public function __construct(IZhlDashboardPage $page)
    {
        $this->page = $page;
    }

    public function PageLoad(UserSession $user)
    {
        $tz = $user->Timezone;

        // Standard-Startdatum = heute + 7 Tage: die meisten Geräte sind „heute" wegen Vorlauf/laufenden
        // Ausleihen nicht frei — ab einer Woche sieht das Raster deutlich freundlicher (mehr Verfügbarkeit) aus.
        $startStr = $this->readDate('start', $tz, 7);
        // „days" = Vorschau-Zeitraum des Rasters (nicht die Ausleihdauer — die wählt man beim Buchen).
        // Mindestens 7 Tage Vorschau, damit man sieht, WANN ein Gerät frei wird (Vorlauf gelb → frei grün).
        $days = max(7, $this->readInt('days', 14, 1, 31));
        $cat = trim((string)$this->readRaw('cat'));
        $search = trim((string)$this->readRaw('q'));

        $start = Date::Parse($startStr, $tz);

        $db = ServiceLocator::GetDatabase();
        $typeAttributeId = $this->lookupTypeAttributeId($db);

        $resourceService = new ResourceService(
            new ResourceRepository(),
            new SchedulePermissionService(PluginManager::Instance()->LoadPermission()),
            new AttributeService(new AttributeRepository()),
            new UserRepository(),
            new AccessoryRepository()
        );
        $service = new ZhlAvailabilityService(
            $resourceService,
            new ResourceAvailability(new ReservationViewRepository()),
            $db,
            $typeAttributeId
        );
        // Immer ALLE Geräte holen (schedule-Filter aus); die Filterung läuft jetzt über kuratierte
        // ZHL-Kategorien (Geräte-Typ-Gruppen), nicht mehr über Schedules.
        $grid = $service->BuildGrid($user, $start, $days, 0, $search);

        // Kategorien = kuratierte ZHL-Gruppen (fest definiert), gemappt auf Geräte-Typen.
        $map = $this->categoryMap();
        $covered = [];
        foreach ($map as $c) {
            foreach ($c['types'] as $t) {
                $covered[$t] = true;
            }
        }

        $rows = $grid['rows'];
        $counts = [];
        $otherCount = 0;
        foreach ($rows as $r) {
            $t = $r->type;
            $key = null;
            if ($t !== null) {
                foreach ($map as $c) {
                    if (in_array($t, $c['types'], true)) {
                        $key = $c['key'];
                        break;
                    }
                }
            }
            if ($key !== null) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            } else {
                $otherCount++;
            }
        }

        $categories = [];
        foreach ($map as $c) {
            $n = $counts[$c['key']] ?? 0;
            if ($n === 0) {
                continue;
            }
            $categories[] = ['key' => $c['key'], 'name' => $c['label'], 'count' => $n, 'note' => $c['note']];
        }
        if ($otherCount > 0) {
            $categories[] = ['key' => 'weitere', 'name' => 'Weitere Geräte', 'count' => $otherCount, 'note' => ''];
        }

        // Aktive Kategorie gegen die FESTEN Kategorie-Schlüssel validieren (nicht gegen die aktuellen
        // Trefferzahlen) — sonst würde eine Kategorie bei leerer Suche aus der Liste fallen und die
        // Ansicht ungewollt auf „Alle" (inkl. Milchglas) zurückspringen.
        $validKeys = array_column($map, 'key');
        $validKeys[] = 'weitere';
        $activeCat = in_array($cat, $validKeys, true) ? $cat : '';

        // Eine Suche hebt die Kategorie-Auswahl auf und sucht global — sonst stünde „keine Geräte",
        // wenn der neue Suchbegriff nicht in die zuvor gewählte Kategorie passt.
        if ($search !== '') {
            $activeCat = '';
        }
        $activeName = '';
        $activeNote = '';
        if ($activeCat === 'weitere') {
            $rows = array_values(array_filter($rows, fn($r) => $r->type === null || !isset($covered[$r->type])));
            $activeName = 'Weitere Geräte';
        } elseif ($activeCat !== '') {
            foreach ($map as $c) {
                if ($c['key'] === $activeCat) {
                    $rows = array_values(array_filter($rows, fn($r) => $r->type !== null && in_array($r->type, $c['types'], true)));
                    $activeName = $c['label'];
                    $activeNote = $c['note'];
                    break;
                }
            }
        }

        // Milchglas nur beim ersten Öffnen der „Alle"-Ansicht OHNE Suche. Bei aktiver Suche oder
        // gewählter Kategorie wäre es störend. Das „nicht erneut zeigen nach Interaktion" macht das
        // clientseitige Script (sessionStorage) in zhl-dashboard.tpl.
        $frosted = ($activeCat === '' && $search === '');

        $end = $start->AddDays($days);
        $this->page->BindDashboard([
            'categories' => $categories,
            'rows' => $rows,
            'activeCat' => $activeCat,
            'activeName' => $activeName,
            'activeNote' => $activeNote,
            'frosted' => $frosted,
            'search' => $search,
            'startInput' => $start->Format('Y-m-d'),
            'days' => $days,
            'isAdmin' => ($user->IsAdmin || $user->IsResourceAdmin || $user->IsScheduleAdmin || $user->IsGroupAdmin),
            'rangeLabel' => $start->ToTimezone($tz)->Format('d.m.Y') . ' – '
                . $end->AddDays(-1)->ToTimezone($tz)->Format('d.m.Y'),
            'totalVisible' => $grid['totalVisible'],
        ]);
    }

    /**
     * Kuratierte ZHL-Kategorien für die „Geräte einzeln"-Seite (fest im Code, ZHL-Entscheidung
     * 2026-06-26). Jede Kategorie mappt auf einen oder mehrere Geräte-Typen (custom_attribute
     * „Geräte-Typ"). Nicht abgedeckte Typen (z. B. Podcast-Mikrofon) landen in „Weitere Geräte".
     */
    private function categoryMap(): array
    {
        return [
            ['key' => 'mikro', 'label' => 'Mikrofone',
                'types' => ['Funkmikrofon (mit zwei Sendern)', 'Podcast-Mikrofon'], 'note' => ''],
            ['key' => 'videostudio', 'label' => 'Videostudio', 'types' => ['Videostudio'], 'note' => ''],
            ['key' => 'smartphone', 'label' => 'Smartphone-Video-Kit', 'types' => ['Smartphone-Video-Kit'], 'note' => ''],
            ['key' => 'kamera', 'label' => 'Kameras',
                'types' => ['Profi-Kamera', 'Einfache Allround-Kamera', 'Objektiv', 'Gimbal', 'Stativ', 'Kleines Kamerastativ', 'Richtmikrofon'], 'note' => ''],
            ['key' => 'moderation', 'label' => 'Moderationsmaterial', 'types' => ['Moderationsmaterial'], 'note' => ''],
            ['key' => 'schnitt', 'label' => 'Schnittcomputer', 'types' => ['Schnitt-/VR-PC'], 'note' => ''],
            ['key' => 'immersive', 'label' => 'Immersive Medien (VR / AR / 3D)',
                'types' => ['VR-Brille', 'AR-Brille', '360-Grad-Kamera', 'Teleskopstange (360°-Kamera)'], 'note' => ''],
            ['key' => 'drohne', 'label' => 'Drohne', 'types' => ['Drohne'], 'note' => ''],
        ];
    }

    private function lookupTypeAttributeId($db)
    {
        $cmd = new AdHocCommand(
            "SELECT custom_attribute_id FROM custom_attributes " .
            "WHERE display_label = @label AND attribute_category = 4 LIMIT 1"
        );
        $cmd->AddParameter(new Parameter('@label', 'Geräte-Typ'));
        $reader = $db->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        return $row ? (int)$row['custom_attribute_id'] : 0;
    }

    private function readRaw($key)
    {
        return isset($_GET[$key]) ? (string)$_GET[$key] : '';
    }

    private function readInt($key, $default, $min, $max)
    {
        $v = $this->readRaw($key);
        if ($v === '' || !is_numeric($v)) {
            return $default;
        }
        $n = (int)$v;
        if ($n < $min) {
            $n = $min;
        }
        if ($n > $max) {
            $n = $max;
        }
        return $n;
    }

    private function readDate($key, $tz, int $defaultOffsetDays = 0)
    {
        $v = $this->readRaw($key);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            return $v;
        }
        return Date::Now()->AddDays($defaultOffsetDays)->ToTimezone($tz)->Format('Y-m-d');
    }
}
