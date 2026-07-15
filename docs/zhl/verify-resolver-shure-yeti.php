<?php

/**
 * Task D — READ-ONLY Verifikations-Harness für den Auto-Prio-Fallback Shure → Yeti (Bundle #6).
 *
 * Zweck: belegen, dass
 *   (1) ZhlBundleResolver bei alt_group='podcastmic', alt_mode='auto' bevorzugt Shure (rid 28) wählt,
 *       aber auf Yeti (rid 29) AUSWEICHT, wenn Shure im Zeitraum belegt ist — UND
 *   (2) ZhlBundleService::GetBundles() das Bundle in genau diesem Fall als „verfügbar" zeigt
 *       (zweite, leicht zu vergessende Wahrheitsquelle: Dashboard-Verfügbarkeit).
 *
 * KEINE echte Reservierung, KEINE DB-Schreibzugriffe. Der „belegte Shure"-Zustand wird über eine
 * injizierte busy-Map simuliert (FakeAvailability) bzw. den freeMap-Parameter von GetBundles().
 * Die ECHTEN Klassen ZhlBundleResolver/ZhlBundleService werden geladen und ausgeführt; nur die
 * Framework-Ränder (DB-Typmap, ResourceService, Availability) sind minimale Stubs.
 *
 * Lauf:  php docs/zhl/verify-resolver-shure-yeti.php
 */

// --- Minimaler LB-Framework-Rand (Stubs nur für das, was die Resolver-/Service-Klassen berühren) ---

if (!defined('ROOT_DIR')) {
    // ZhlBundleService.php macht require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlTypeInfo.php').
    define('ROOT_DIR', dirname(__DIR__, 2) . '/');
}

class CustomAttributeCategory
{
    const RESOURCE = 4;
}
class ResourceStatus
{
    const HIDDEN = 2;
}

class Parameter
{
    public $name;
    public $value;
    public function __construct($name, $value)
    {
        $this->name = $name;
        $this->value = $value;
    }
}
class AdHocCommand
{
    public $sql;
    public $params = [];
    public function __construct($sql)
    {
        $this->sql = $sql;
    }
    public function AddParameter(Parameter $p)
    {
        $this->params[$p->name] = $p->value;
    }
}

/** Reader über vordefinierte Zeilen. */
class FakeReader
{
    private $rows;
    private $i = 0;
    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }
    public function GetRow()
    {
        return $this->i < count($this->rows) ? $this->rows[$this->i++] : false;
    }
    public function Free()
    {
    }
}

/**
 * Fake-DB: routet Queries grob nach SQL-Inhalt. Deckt ab:
 *  - Resolver::buildTypeMap (custom_attribute_values JOIN custom_attributes ... Geräte-Typ)
 *  - ZhlBundleService: zhl_bundle, zhl_bundle_item
 *  - ZhlTypeInfo::Map (zhl_type_info o.ä.) → leer
 */
class FakeDatabase
{
    private $typeMap;   // rid => typeLabel
    private $bundles;   // rows
    private $items;     // rows
    public function __construct(array $typeMap, array $bundles, array $items)
    {
        $this->typeMap = $typeMap;
        $this->bundles = $bundles;
        $this->items = $items;
    }
    public function Query(AdHocCommand $cmd)
    {
        $sql = $cmd->sql;
        if (strpos($sql, 'custom_attribute_values') !== false && strpos($sql, 'display_label') !== false) {
            $rows = [];
            foreach ($this->typeMap as $rid => $t) {
                $rows[] = ['rid' => $rid, 't' => $t];
            }
            return new FakeReader($rows);
        }
        if (strpos($sql, 'FROM zhl_bundle ') !== false || strpos($sql, 'FROM zhl_bundle WHERE') !== false) {
            return new FakeReader($this->bundles);
        }
        if (strpos($sql, 'FROM zhl_bundle_item') !== false) {
            return new FakeReader($this->items);
        }
        // ZhlTypeInfo::Map o.ä. → leer (kein Info-Material relevant).
        return new FakeReader([]);
    }
    public function Execute($cmd)
    {
        throw new Exception('READ-ONLY Harness: Execute() darf nie aufgerufen werden! SQL=' . $cmd->sql);
    }
}

/**
 * UserSession-Stub: ResolvePhase() typhintet $user als UserSession und reicht ihn nur an
 * ResourceService::GetAllResources(false, $user) weiter (unser FakeResourceService ignoriert ihn).
 * Echter Klassenname muss existieren, damit der Typehint passt.
 */
class UserSession
{
    public $UserId;
    public function __construct($userId = 1)
    {
        $this->UserId = $userId;
    }
}

class ServiceLocator
{
    private static $db;
    public static function SetDatabase($db)
    {
        self::$db = $db;
    }
    public static function GetDatabase()
    {
        return self::$db;
    }
}

/** ResourceDto-Stub (nur die vom Resolver genutzten Getter/Felder). */
class FakeResource
{
    private $id;
    private $sched;
    private $status;
    public $CanBook;
    public function __construct($id, $sched = 5, $canBook = true, $status = 0)
    {
        $this->id = $id;
        $this->sched = $sched;
        $this->CanBook = $canBook;
        $this->status = $status;
    }
    public function GetId()
    {
        return $this->id;
    }
    public function GetScheduleId()
    {
        return $this->sched;
    }
    public function GetStatusId()
    {
        return $this->status;
    }
}

class FakeResourceService
{
    private $resources;
    public function __construct(array $resources)
    {
        $this->resources = $resources;
    }
    public function GetAllResources($includeInaccessible, $user)
    {
        return $this->resources;
    }
}

/** Belegungs-Item (nur GetStartDate/GetEndDate). */
class FakeItem
{
    private $s;
    private $e;
    public function __construct($s, $e)
    {
        $this->s = $s;
        $this->e = $e;
    }
    public function GetStartDate()
    {
        return $this->s;
    }
    public function GetEndDate()
    {
        return $this->e;
    }
}

/** Minimaler Date-Stub mit LessThan/GreaterThan (Unix-TS-basiert). */
class Date
{
    public $ts;
    public function __construct($spec)
    {
        $this->ts = is_int($spec) ? $spec : strtotime($spec);
    }
    public function LessThan(Date $o)
    {
        return $this->ts < $o->ts;
    }
    public function GreaterThan(Date $o)
    {
        return $this->ts > $o->ts;
    }
    public static function Parse($s, $tz = null)
    {
        return new Date($s);
    }
}

/** FakeAvailability: gibt je rid die injizierten Busy-Items zurück (simuliert „Shure belegt"). */
class FakeAvailability
{
    private $busyByResource; // rid => [ [startTs,endTs], ... ]
    public function __construct(array $busyByResource)
    {
        $this->busyByResource = $busyByResource;
    }
    public function GetItemsBetween(Date $begin, Date $end, array $rids)
    {
        $out = [];
        foreach ($rids as $rid) {
            foreach ($this->busyByResource[$rid] ?? [] as $iv) {
                $out[] = new FakeItem(new Date($iv[0]), new Date($iv[1]));
            }
        }
        return $out;
    }
}

// ResourceService/ResourceAvailability sind typhint-Parameter im Resolver-Konstruktor →
// echte Klassen-Namen müssen existieren. Wir aliasen unsere Fakes auf die erwarteten Typen.
class_alias('FakeResourceService', 'ResourceService');
class_alias('FakeAvailability', 'ResourceAvailability');

require_once ROOT_DIR . 'lib/Application/Zhl/ZhlBundleResolver.php';
require_once ROOT_DIR . 'lib/Application/Zhl/ZhlBundleService.php';

// --- Testdaten: Bundle #6 „Podcast aufnehmen" mit Auto-Prio podcastmic (Shure 28 vor Yeti 29) ---

const SHURE = 28;
const YETI = 29;

$typeMap = [
    SHURE => 'Podcast-Mikrofon (Shure)',
    YETI => 'Podcast-Mikrofon (Yeti)',
];
$resources = [
    new FakeResource(SHURE, 5, true, 0),
    new FakeResource(YETI, 5, true, 0),
];

// Items wie nach Migration 021: zwei Auto-Optionen, sort_order Shure=0, Yeti=1.
$items = [
    ['id' => 37, 'type_label' => 'Podcast-Mikrofon (Shure)', 'quantity' => 1, 'required' => 1, 'alt_group' => 'podcastmic', 'alt_mode' => 'auto', 'phase' => 'main', 'meta' => null, 'specific_resource_id' => null],
    ['id' => 99, 'type_label' => 'Podcast-Mikrofon (Yeti)', 'quantity' => 1, 'required' => 1, 'alt_group' => 'podcastmic', 'alt_mode' => 'auto', 'phase' => 'main', 'meta' => null, 'specific_resource_id' => null],
];

$user = new UserSession(1);

$begin = new Date('2026-07-10 09:00:00');
$end = new Date('2026-07-10 17:00:00');

$pass = true;
function ok($name, $cond)
{
    global $pass;
    echo ($cond ? "PASS" : "FAIL") . "  $name\n";
    $pass = $pass && $cond;
}

// === Szenario 1: BEIDE frei → Resolver bevorzugt Shure ===
ServiceLocator::SetDatabase(new FakeDatabase($typeMap, [], []));
$resolverFree = new ZhlBundleResolver(new ResourceService($resources), new ResourceAvailability([]));
$phaseFree = $resolverFree->ResolvePhase($items, $begin, $end, [], $user, 'main');
$idsFree = $phaseFree->AllResourceIds();
ok('beide frei: Phase erfüllbar', $phaseFree->satisfiable);
ok('beide frei: Resolver wählt Shure (28)', $idsFree === [SHURE]);

// === Szenario 2: SHURE BELEGT, YETI FREI → Resolver weicht auf Yeti aus ===
ServiceLocator::SetDatabase(new FakeDatabase($typeMap, [], []));
$busy = [SHURE => [['2026-07-10 08:00:00', '2026-07-10 18:00:00']]]; // Shure den ganzen Tag belegt
$resolverBusy = new ZhlBundleResolver(new ResourceService($resources), new ResourceAvailability($busy));
$phaseBusy = $resolverBusy->ResolvePhase($items, $begin, $end, [], $user, 'main');
$idsBusy = $phaseBusy->AllResourceIds();
ok('Shure belegt: Phase erfüllbar (Fallback)', $phaseBusy->satisfiable);
ok('Shure belegt: Resolver weicht auf Yeti (29) aus', $idsBusy === [YETI]);

// === Szenario 3: BEIDE belegt → Phase NICHT erfüllbar (klarer Fehler) ===
ServiceLocator::SetDatabase(new FakeDatabase($typeMap, [], []));
$busyBoth = [
    SHURE => [['2026-07-10 08:00:00', '2026-07-10 18:00:00']],
    YETI => [['2026-07-10 08:00:00', '2026-07-10 18:00:00']],
];
$resolverBoth = new ZhlBundleResolver(new ResourceService($resources), new ResourceAvailability($busyBoth));
$phaseBoth = $resolverBoth->ResolvePhase($items, $begin, $end, [], $user, 'main');
ok('beide belegt: Phase NICHT erfüllbar', !$phaseBoth->satisfiable);
ok('beide belegt: AllResourceIds leer', $phaseBoth->AllResourceIds() === []);

// === Szenario 4: ZhlBundleService::GetBundles() zeigt Bundle #6 als verfügbar, wenn Shure belegt,
//                 Yeti aber frei (typeFreeMap: Shure=0, Yeti=1). Zweite Wahrheitsquelle (Dashboard). ===
$bundleRows = [
    ['id' => 6, 'name' => 'Podcast aufnehmen', 'use_case' => '', 'difficulty' => 'leicht', 'hint' => '', 'einweisung_level' => 'keine', 'einweisung_text' => '', 'einweisung_url' => '', 'offer_schnitt' => 0],
];
$bundleItems = [
    ['bundle_id' => 6, 'type_label' => 'Podcast-Mikrofon (Shure)', 'quantity' => 1, 'required' => 1, 'alt_group' => 'podcastmic', 'note' => ''],
    ['bundle_id' => 6, 'type_label' => 'Podcast-Mikrofon (Yeti)', 'quantity' => 1, 'required' => 1, 'alt_group' => 'podcastmic', 'note' => ''],
];
$svcDb = new FakeDatabase($typeMap, $bundleRows, $bundleItems);
$svc = new ZhlBundleService($svcDb);

// Shure belegt (0 frei), Yeti frei (1) → Auto-Gruppe muss als verfügbar gelten.
$views = $svc->GetBundles(['Podcast-Mikrofon (Shure)' => 0, 'Podcast-Mikrofon (Yeti)' => 1]);
$b6 = null;
foreach ($views as $v) {
    if ((int)$v->id === 6) {
        $b6 = $v;
    }
}
ok('GetBundles: Bundle #6 vorhanden', $b6 !== null);
ok('GetBundles: Bundle #6 verfügbar (Yeti frei, obwohl Shure belegt)', $b6 !== null && $b6->available === true);

// Gegenprobe: BEIDE Optionen 0 frei → Bundle NICHT verfügbar.
$views2 = $svc->GetBundles(['Podcast-Mikrofon (Shure)' => 0, 'Podcast-Mikrofon (Yeti)' => 0]);
$b6b = null;
foreach ($views2 as $v) {
    if ((int)$v->id === 6) {
        $b6b = $v;
    }
}
ok('GetBundles: beide 0 frei → Bundle #6 NICHT verfügbar', $b6b !== null && $b6b->available === false);

echo "\n" . ($pass ? "ALLE TESTS PASS" : "FEHLER — Resolver/Service-Fallback NICHT belegt") . "\n";
exit($pass ? 0 : 1);
