<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/ZhlAssistantPresenter.php');

/**
 * Vorhaben-Assistent (Dashboard v3, v1). SecurePage, pageDepth 0, read-only.
 */
class ZhlAssistantPage extends SecurePage implements IZhlAssistantPage
{
    private $presenter;

    public function __construct()
    {
        parent::__construct('Assistent');
        $this->presenter = new ZhlAssistantPresenter($this);
    }

    public function PageLoad()
    {
        $user = ServiceLocator::GetServer()->GetUserSession();
        $this->presenter->PageLoad($user);
        $this->Set('HideNavBar', true);
        $this->Display('zhl-assistant.tpl');
    }

    public function BindAssistant(array $vm)
    {
        foreach ($vm as $key => $value) {
            $this->Set(ucfirst($key), $value);
        }
    }
}

interface IZhlAssistantPage
{
    public function BindAssistant(array $vm);
}
