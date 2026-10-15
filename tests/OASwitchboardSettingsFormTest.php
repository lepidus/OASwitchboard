<?php

import('lib.pkp.tests.PKPTestCase');
import('classes.core.Request');
import('plugins.generic.OASwitchboard.OASwitchboardPlugin');
import('plugins.generic.OASwitchboard.classes.settings.OASwitchboardSettingsForm');
import('plugins.generic.OASwitchboard.classes.exceptions.ConnectionFailedException');

class OASwitchboardSettingsFormTest extends PKPTestCase
{
    protected function getMockedRegistryKeys()
    {
        return array_merge(parent::getMockedRegistryKeys(), ['request']);
    }

    private function createPlugin()
    {
        $plugin = $this->createMock(OASwitchboardPlugin::class);
        $plugin->method('getSetting')
            ->willReturnCallback(function ($contextId, $name) {
                return $name === 'username' ? 'stored@example.com' : null;
            });
        $plugin->method('getTemplateResource')->willReturn('settingsForm.tpl');
        return $plugin;
    }

    private function createFormFailingAuthorizationWith(Exception $exception, ArrayObject $notifications)
    {
        $apiClient = $this->createMock(OASwitchboardAPIClient::class);
        $apiClient->method('getAuthorization')->willThrowException($exception);

        return new class ($this->createPlugin(), $apiClient, $notifications) extends OASwitchboardSettingsForm {
            private $apiClient;
            private $notifications;

            public function __construct($plugin, $apiClient, $notifications)
            {
                parent::__construct($plugin, 1);
                $this->setData('OASUsername', 'user@example.com');
                $this->setData('OASPassword', 'password');
                $this->apiClient = $apiClient;
                $this->notifications = $notifications;
            }

            protected function createAPIClient()
            {
                return $this->apiClient;
            }

            protected function notifyCredentialsFailure(string $message): void
            {
                $this->notifications->append($message);
            }
        };
    }

    public function testConnectionFailureShouldBeReportedInsteadOfInvalidCredentials()
    {
        $notifications = new ArrayObject();
        $form = $this->createFormFailingAuthorizationWith(new ConnectionFailedException('Could not connect to OA Switchboard.'), $notifications);

        $this->assertFalse($form->validateAPICredentials());
        $this->assertSame(['Could not connect to OA Switchboard.'], $notifications->getArrayCopy());
    }

    public function testRejectedCredentialsShouldBeReportedAsInvalidCredentials()
    {
        $notifications = new ArrayObject();
        $form = $this->createFormFailingAuthorizationWith(new OASwitchboardException('rejected'), $notifications);

        $this->assertFalse($form->validateAPICredentials());
        $this->assertSame([__('plugins.generic.OASwitchboard.settings.apiAuthenticatorFailed')], $notifications->getArrayCopy());
    }

    public function testUsernameFieldShouldStartWithTheStoredUsername()
    {
        $form = new OASwitchboardSettingsForm($this->createPlugin(), 1);

        $form->initData();

        $this->assertSame('stored@example.com', $form->getData('OASUsername'));
    }

    public function testUsernameFieldShouldKeepWhatWasTypedAfterAFailedSave()
    {
        $_POST['OASUsername'] = 'typed@example.com';
        $request = new Request();
        Registry::set('request', $request);
        $form = new OASwitchboardSettingsForm($this->createPlugin(), 1);

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
