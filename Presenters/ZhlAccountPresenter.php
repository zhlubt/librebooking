<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Database/namespace.php');
require_once(ROOT_DIR . 'Domain/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlCertTypeInfo.php');

/**
 * Presenter „Konto". Liest die eigenen Profildaten des angemeldeten Nutzers
 * (Name, E-Mail aus der UserSession; Telefon/Einrichtung/Position aus dem
 * UserRepository) und reicht sie read-only an die Page. Bearbeitung läuft über
 * die nativen Seiten profile.php / password.php — hier keine Schreibzugriffe.
 */
class ZhlAccountPresenter
{
    /** @var IZhlAccountPage */
    private $page;

    /** @var IUserRepository */
    private $userRepository;

    public function __construct(IZhlAccountPage $page, ?IUserRepository $userRepository = null)
    {
        $this->page = $page;
        $this->userRepository = $userRepository ?? new UserRepository();
    }

    public function PageLoad(UserSession $session)
    {
        $phone = '';
        $organization = '';
        $position = '';

        try {
            $user = $this->userRepository->LoadById($session->UserId);
            if ($user !== null) {
                $phone = (string)$user->GetAttribute(UserAttribute::Phone);
                $organization = (string)$user->GetAttribute(UserAttribute::Organization);
                $position = (string)$user->GetAttribute(UserAttribute::Position);
            }
        } catch (Exception $e) {
            // Zusatzfelder sind optional — Name/E-Mail kommen aus der Session.
            Log::Debug('ZHL-Konto: Profildetails konnten nicht geladen werden: %s', $e->getMessage());
        }

        $certificates = $this->loadCertificates((int)$session->UserId, $session->Timezone);

        $this->page->BindAccount([
            'firstName' => (string)$session->FirstName,
            'lastName' => (string)$session->LastName,
            'email' => (string)$session->Email,
            'phone' => $phone,
            'organization' => $organization,
            'position' => $position,
            'languageCode' => (string)$session->LanguageCode,
            'certificates' => $certificates,
            'hasCertificates' => count($certificates) > 0,
            'isAdmin' => (bool)$session->IsAdmin,
        ]);
    }

    /**
     * Erworbene Zertifikate (= absolvierte Einführungen) des Nutzers aus dem benannten
     * v-cert-System: zhl_cert_grant × zhl_cert_type, plus die je Zertifikat abgedeckten
     * Geräte. Nur aktive Zertifikatstypen (entspricht der echten Buchungs-Freischaltung).
     * Best effort — fehlen die Tabellen, bleibt die Liste leer (kein Fehler).
     *
     * @return array[] [{name, grantedLabel, expiryLabel, expired, devices}]
     */
    private function loadCertificates(int $userId, $tz): array
    {
        $out = [];
        try {
            $db = ServiceLocator::GetDatabase();

            $cmd = new AdHocCommand(
                'SELECT g.cert_type_id, t.name, g.granted_at, g.expires_at ' .
                'FROM zhl_cert_grant g JOIN zhl_cert_type t ON t.id = g.cert_type_id ' .
                'WHERE g.user_id = @uid AND t.active = 1 ORDER BY t.sort_order, t.name'
            );
            $cmd->AddParameter(new Parameter('@uid', $userId));
            $reader = $db->Query($cmd);

            $nowUtc = gmdate('Y-m-d H:i:s');
            $rows = [];
            $typeIds = [];
            while ($row = $reader->GetRow()) {
                $typeId = (int)$row['cert_type_id'];
                $typeIds[$typeId] = true;

                $grantedLabel = '';
                if (!empty($row['granted_at'])) {
                    $grantedLabel = Date::Parse((string)$row['granted_at'], 'UTC')->ToTimezone($tz)->Format('d.m.Y');
                }
                $expiryLabel = '';
                $expired = false;
                if (!empty($row['expires_at'])) {
                    $expired = ((string)$row['expires_at'] < $nowUtc);
                    $expiryLabel = Date::Parse((string)$row['expires_at'], 'UTC')->ToTimezone($tz)->Format('d.m.Y');
                }

                $rows[$typeId] = [
                    'name' => (string)$row['name'],
                    'grantedLabel' => $grantedLabel,
                    'expiryLabel' => $expiryLabel,
                    'expired' => $expired,
                    'devices' => [],
                ];
            }
            $reader->Free();

            if (!empty($typeIds)) {
                // Abgedeckte Geräte je Zertifikat (nur für die gehaltenen Typen).
                $ids = implode(',', array_map('intval', array_keys($typeIds)));
                $reader = $db->Query(new AdHocCommand(
                    'SELECT ctr.cert_type_id, r.name FROM zhl_cert_type_resource ctr ' .
                    'JOIN resources r ON r.resource_id = ctr.resource_id ' .
                    'WHERE ctr.cert_type_id IN (' . $ids . ') ORDER BY r.name'
                ));
                while ($row = $reader->GetRow()) {
                    $typeId = (int)$row['cert_type_id'];
                    if (isset($rows[$typeId])) {
                        $rows[$typeId]['devices'][] = (string)$row['name'];
                    }
                }
                $reader->Free();
            }

            // D3: vertrauliche Zusatz-Infos je Zertifikat — NUR für eigene, GÜLTIGE (nicht abgelaufene)
            // Grants. ForUser() gated serverseitig; hier zusätzlich gegen das expired-Flag abgesichert.
            $confidential = ZhlCertTypeInfo::ForUser($db, $userId);
            foreach ($rows as $typeId => &$r) {
                if (!$r['expired'] && isset($confidential[$typeId])) {
                    $c = $confidential[$typeId];
                    $r['confidential'] = [
                        'has' => true,
                        'code' => $c['transponder_code'],
                        'news' => $c['news_text'],
                        'docUrl' => (preg_match('#^https?://#i', $c['doc_url']) ? $c['doc_url'] : ''),
                    ];
                } else {
                    $r['confidential'] = ['has' => false, 'code' => '', 'news' => '', 'docUrl' => ''];
                }
            }
            unset($r);

            $out = array_values($rows);
        } catch (Exception $e) {
            Log::Debug('ZHL-Konto: Zertifikate konnten nicht geladen werden: %s', $e->getMessage());
            return [];
        }
        return $out;
    }
}
