<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\plugins\generic\OASwitchboard\classes\settings\OASwitchboardSettingsForm;
use APP\plugins\generic\OASwitchboard\OASwitchboardPlugin;
use PKP\tests\PKPTestCase;

class OASwitchboardSettingsFormTest extends PKPTestCase
{
    private function createForm(): OASwitchboardSettingsForm
    {
        $plugin = $this->createMock(OASwitchboardPlugin::class);
        $plugin->method('getSetting')
            ->willReturnCallback(fn ($contextId, $name) => $name === 'username' ? 'stored@example.com' : null);
        $plugin->method('getTemplateResource')->willReturn('settingsForm.tpl');
        return new OASwitchboardSettingsForm($plugin, 1);
    }

    public function testUsernameFieldShouldStartWithTheStoredUsername()
    {
        $form = $this->createForm();

        $form->initData();

        $this->assertSame('stored@example.com', $form->getData('OASUsername'));
    }

    public function testUsernameFieldShouldKeepWhatWasTypedAfterAFailedSave()
    {
        $this->mockRequest();
        $_POST['OASUsername'] = 'typed@example.com';
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
