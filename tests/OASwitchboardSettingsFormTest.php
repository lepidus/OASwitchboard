<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\plugins\generic\OASwitchboard\classes\api\OASwitchboardAPIClient;
use APP\plugins\generic\OASwitchboard\classes\exceptions\ConnectionFailedException;
use APP\plugins\generic\OASwitchboard\classes\exceptions\OASwitchboardException;
use APP\plugins\generic\OASwitchboard\classes\settings\OASwitchboardSettingsForm;
use APP\core\Request;
use APP\plugins\generic\OASwitchboard\OASwitchboardPlugin;
use PKP\core\Registry;
use PKP\tests\PKPTestCase;

class OASwitchboardSettingsFormTest extends PKPTestCase
{
    protected function getMockedRegistryKeys(): array
    {
        return [...parent::getMockedRegistryKeys(), 'request'];
    }

    private function createForm(): OASwitchboardSettingsForm
    {
        $plugin = $this->createMock(OASwitchboardPlugin::class);
        $plugin->method('getSetting')
            ->willReturnCallback(fn ($contextId, $name) => $name === 'username' ? 'stored@example.com' : null);
        $plugin->method('getTemplateResource')->willReturn('settingsForm.tpl');
        return new OASwitchboardSettingsForm($plugin, 1);
    }

    private function createFormFailingAuthorizationWith(\Exception $exception, array &$notifications): OASwitchboardSettingsForm
    {
        $plugin = $this->createMock(OASwitchboardPlugin::class);
        $plugin->method('getTemplateResource')->willReturn('settingsForm.tpl');
        $apiClient = $this->createMock(OASwitchboardAPIClient::class);
        $apiClient->method('getAuthorization')->willThrowException($exception);

        return new class ($plugin, $apiClient, $notifications) extends OASwitchboardSettingsForm {
            private $apiClient;
            private $notifications;

            public function __construct($plugin, $apiClient, array &$notifications)
            {
                parent::__construct($plugin, 1);
                $this->setData('OASUsername', 'user@example.com');
                $this->setData('OASPassword', 'password');
                $this->apiClient = $apiClient;
                $this->notifications = &$notifications;
            }

            protected function createAPIClient(): OASwitchboardAPIClient
            {
                return $this->apiClient;
            }

            protected function notifyCredentialsFailure(string $message): void
            {
                $this->notifications[] = $message;
            }
        };
    }

    public function testConnectionFailureShouldBeReportedInsteadOfInvalidCredentials()
    {
        $notifications = [];
        $form = $this->createFormFailingAuthorizationWith(new ConnectionFailedException('Could not connect to OA Switchboard.'), $notifications);

        $this->assertFalse($form->validateAPICredentials());
        $this->assertSame(['Could not connect to OA Switchboard.'], $notifications);
    }

    public function testRejectedCredentialsShouldBeReportedAsInvalidCredentials()
    {
        $notifications = [];
        $form = $this->createFormFailingAuthorizationWith(new OASwitchboardException('rejected'), $notifications);

        $this->assertFalse($form->validateAPICredentials());
        $this->assertSame(['##plugins.generic.OASwitchboard.settings.apiAuthenticatorFailed##'], $notifications);
    }

    public function testUsernameFieldShouldStartWithTheStoredUsername()
    {
        $form = $this->createForm();

        $form->initData();

        $this->assertSame('stored@example.com', $form->getData('OASUsername'));
    }

    public function testUsernameFieldShouldKeepWhatWasTypedAfterAFailedSave()
    {
        $_POST['OASUsername'] = 'typed@example.com';
        $request = new Request();
        Registry::set('request', $request);
        $form = $this->createForm();

        try {
            $form->initData();
            $form->readInputData();
        } finally {
            unset($_POST['OASUsername']);
        }

        $this->assertSame('typed@example.com', $form->getData('OASUsername'));
        $this->assertNull($form->getData('username'));
    }
}
