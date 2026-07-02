<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Database/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlTerminRequest.php');
require_once(ROOT_DIR . 'Presenters/ZhlTerminRequestEmail.php');
require_once(ROOT_DIR . 'Web/zhl-audit-lib.php');

/**
 * ZHL B — Presenter „Wunschtermin anfragen". Lädt das angefragte Gerät/Bundle, zeigt das Formular
 * (Wunsch-Zeitraum + Projekt + Nachricht) und legt beim Absenden eine Anfrage an (Anfrage-only,
 * kein Hard-Hold) + Mail ans Medien-Team (CC Nutzer). Min-Fristen gelten weiter für den normalen
 * Buchungsweg; diese Anfrage umgeht sie NICHT — sie ist nur ein strukturierter Wunsch.
 */
class ZhlTerminAnfragePresenter
{
    private $page;

    public function __construct($page)
    {
        $this->page = $page;
    }

    /** Zweck der Anfrage: 'return' (Rückgabetermin) oder 'einf' (Default, Einführung). */
    private function purpose(): string
    {
        return (($_REQUEST['purpose'] ?? '') === 'return') ? 'return' : 'einf';
    }

    public function PageLoad(UserSession $user)
    {
        if (isset($_GET['sent'])) {
            $this->page->Bind(['mode' => 'sent', 'purpose' => $this->purpose()]);
            return;
        }

        $tz = $user->Timezone;
        $db = ServiceLocator::GetDatabase();
        $ctx = $this->loadContext($db, $user);
        if ($ctx === null) {
            $this->page->GoTo('zhl-dashboard.php');
            return;
        }

        // Vorschlag: Start übermorgen, Ende +7 Tage (nur als Default — der Nutzer wählt frei).
        $start = Date::Now()->ToTimezone($tz)->AddDays(2);
        $end = $start->AddDays(7);
        $this->bindForm($user, $ctx, $start->Format('Y-m-d'), $end->Format('Y-m-d'), trim((string)($_GET['pt'] ?? '')), '', []);
    }

    public function HandlePost(UserSession $user)
    {
        $tz = $user->Timezone;
        $db = ServiceLocator::GetDatabase();
        $ctx = $this->loadContext($db, $user);
        if ($ctx === null) {
            $this->page->GoTo('zhl-dashboard.php');
            return;
        }

        $startStr = trim((string)($_POST['wunschStart'] ?? ''));
        $endStr = trim((string)($_POST['wunschEnd'] ?? ''));
        $projectTitle = trim((string)($_POST['projectTitle'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));

        $errors = [];
        if (!$this->isYmd($startStr)) {
            $errors[] = 'Bitte ein gültiges Wunsch-Startdatum wählen.';
        }
        if (!$this->isYmd($endStr)) {
            $errors[] = 'Bitte ein gültiges Wunsch-Enddatum wählen.';
        }
        if (empty($errors)) {
            $today = Date::Now()->ToTimezone($tz)->Format('Y-m-d');
            if ($startStr < $today) {
                $errors[] = 'Der Wunsch-Start darf nicht in der Vergangenheit liegen.';
            }
            if ($endStr < $startStr) {
                $errors[] = 'Das Wunsch-Ende darf nicht vor dem Start liegen.';
            }
        }
        if (mb_strlen($message) > 4000) {
            $message = mb_substr($message, 0, 4000);
        }
        if (!empty($errors)) {
            $this->bindForm($user, $ctx, $startStr, $endStr, $projectTitle, $message, $errors);
            return;
        }

        // Wunsch-Zeitraum als UTC (Start 00:00, Ende 23:59 lokaler Zeit).
        $startUtc = Date::Parse($startStr . ' 00:00:00', $tz)->ToTimezone('UTC')->Format('Y-m-d H:i:s');
        $endUtc = Date::Parse($endStr . ' 23:59:00', $tz)->ToTimezone('UTC')->Format('Y-m-d H:i:s');

        $purpose = $this->purpose();
        $reqId = ZhlTerminRequest::Create($db, [
            'user_id' => (int)$user->UserId,
            'kind' => $ctx['kind'],
            'purpose' => $purpose,
            'resource_id' => $ctx['kind'] === 'single' ? $ctx['id'] : null,
            'bundle_id' => $ctx['kind'] === 'bundle' ? $ctx['id'] : null,
            'label' => $ctx['label'],
            'desired_start' => $startUtc,
            'desired_end' => $endUtc,
            'project_title' => $projectTitle,
            'message' => $message !== '' ? $message : null,
        ]);

        if ($reqId > 0) {
            zhl_audit_log(array_merge(zhl_audit_actor($user), [
                'action' => 'termin.request.create',
                'entity_type' => $ctx['kind'] === 'bundle' ? 'bundle' : 'resource',
                'entity_id' => (string)$ctx['id'],
                'detail' => ['label' => $ctx['label'], 'from' => $startStr, 'to' => $endStr, 'purpose' => $purpose],
            ]));
            $this->notifyTeam($user, $ctx, $startStr, $endStr, $projectTitle, $message, $purpose);
        }

        $this->page->GoTo('zhl-termin-anfrage.php?sent=1' . ($purpose === 'return' ? '&purpose=return' : ''));
    }

    /** Gerät/Bundle aus ?rid / ?bundle (GET oder POST) laden + auf aktiv prüfen. @return array|null */
    private function loadContext($db, UserSession $user): ?array
    {
        $rid = (int)($_REQUEST['rid'] ?? 0);
        $bid = (int)($_REQUEST['bundle'] ?? 0);
        if ($rid > 0) {
            $cmd = new AdHocCommand('SELECT name FROM resources WHERE resource_id = @rid AND status_id = 1');
            $cmd->AddParameter(new Parameter('@rid', $rid));
            $reader = $db->Query($cmd);
            $row = $reader->GetRow();
            $reader->Free();
            if ($row !== false) {
                return ['kind' => 'single', 'id' => $rid, 'label' => (string)$row['name']];
            }
        } elseif ($bid > 0) {
            $cmd = new AdHocCommand('SELECT name FROM zhl_bundle WHERE id = @bid AND active = 1');
            $cmd->AddParameter(new Parameter('@bid', $bid));
            $reader = $db->Query($cmd);
            $row = $reader->GetRow();
            $reader->Free();
            if ($row !== false) {
                return ['kind' => 'bundle', 'id' => $bid, 'label' => (string)$row['name']];
            }
        }
        return null;
    }

    private function bindForm(UserSession $user, array $ctx, string $startStr, string $endStr, string $projectTitle, string $message, array $errors): void
    {
        $this->page->Bind([
            'mode' => 'form',
            'purpose' => $this->purpose(),
            'kind' => $ctx['kind'],
            'ctxId' => $ctx['id'],
            'label' => $ctx['label'],
            'wunschStart' => $startStr,
            'wunschEnd' => $endStr,
            'projectTitle' => $projectTitle,
            'message' => $message,
            'errors' => $errors,
            'recipient' => ZhlTerminRequest::RecipientEmail(),
        ]);
    }

    /** Mail ans Medien-Team (To = medien_email, test-sicher), CC an den Anfragenden. */
    private function notifyTeam(UserSession $user, array $ctx, string $startStr, string $endStr, string $projectTitle, string $message, string $purpose = 'einf'): void
    {
        try {
            $to = ZhlTerminRequest::RecipientEmail();
            if ($to === '') {
                Log::Error('ZHL-TerminRequest: keine medien_email konfiguriert — Mail übersprungen.');
                return;
            }
            $isReturn = $purpose === 'return';
            $wunschWort = $isReturn ? 'Rückgabe-Terminwunsch' : 'Einführungs-Terminwunsch';
            $name = trim($user->FirstName . ' ' . $user->LastName);
            $lines = [
                'Es liegt ein neuer ' . $wunschWort . ' für die Medienausleihe vor.',
                '',
                'Bitte im Admin-Bereich bearbeiten: Termine anbieten (mit „wer ' .
                    ($isReturn ? 'nimmt die Rückgabe entgegen' : 'macht die Einführung') . '")',
                'und dem Nutzer zur Auswahl schicken — oder ablehnen, wenn es nicht möglich ist:',
                '  ' . $this->absoluteBase() . 'zhl-termin-anfrage-admin.php',
                '',
                ($ctx['kind'] === 'bundle' ? 'Bundle:          ' : 'Gerät:           ') . $ctx['label'],
                'Gewünschter Zeitraum: ' . $startStr . ' bis ' . $endStr,
                'Projekt:         ' . ($projectTitle !== '' ? $projectTitle : '—'),
                'Anfragende(r):   ' . $name . ' <' . $user->Email . '>',
                '',
                'Nachricht:',
                ($message !== '' ? $message : '(keine)'),
                '',
                'Hinweis: Diese Anfrage hält das Gerät NICHT — bitte konkrete ' .
                    ($isReturn ? 'Rückgabetermine' : 'Einführungstermine') . ' anbieten.',
                '',
                'ZHL Medienausleihe',
            ];
            $body = implode("\n", $lines);
            $toList = [new EmailAddress($to, 'ZHL Medien')];
            $cc = [];
            if (trim((string)$user->Email) !== '') {
                $cc[] = new EmailAddress($user->Email, $name !== '' ? $name : $user->Email);
            }
            $subject = 'ZHL Medienausleihe — ' . $wunschWort . ': ' . $ctx['label'];
            $lang = !empty($user->LanguageCode) ? $user->LanguageCode : null;
            ServiceLocator::GetEmailService()->Send(new ZhlTerminRequestEmail($toList, $cc, $subject, $body, $lang));
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: Team-Mail fehlgeschlagen: %s', $e);
        }
    }

    private function absoluteBase(): string
    {
        $cfg = Configuration::Instance();
        $url = rtrim((string)$cfg->GetScriptUrl(), '/');
        return $url !== '' ? $url . '/' : '/Web/';
    }

    private function isYmd(string $s): bool
    {
        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && checkdate((int)substr($s, 5, 2), (int)substr($s, 8, 2), (int)substr($s, 0, 4));
    }
}
