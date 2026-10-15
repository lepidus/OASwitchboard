<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\core\Request;
use APP\journal\Journal;
use APP\plugins\generic\OASwitchboard\classes\settings\OASwitchboardManage;
use APP\plugins\generic\OASwitchboard\classes\settings\OASwitchboardSettingsForm;
use APP\plugins\generic\OASwitchboard\OASwitchboardPlugin;
use PKP\tests\PKPTestCase;

class OASwitchboardManageTest extends PKPTestCase
{
    private function createRequest(array $userVars): Request
    {
        $context = new Journal();
        $context->setId(1);
        $request = $this->createMock(Request::class);
        $request->method('getUserVar')
            ->willReturnCallback(fn ($name) => $userVars[$name] ?? null);
        $request->method('getContext')->willReturn($context);
        return $request;
    }

    private function createManage(OASwitchboardPlugin $plugin, ?OASwitchboardSettingsForm $form = null): OASwitchboardManage
    {
        return new class ($plugin, $form) extends OASwitchboardManage {
            private ?OASwitchboardSettingsForm $formForTests;

            public function __construct(OASwitchboardPlugin $plugin, ?OASwitchboardSettingsForm $formForTests)
            {
                parent::__construct($plugin);
                $this->formForTests = $formForTests;
            }

            protected function createSettingsForm(int $contextId): OASwitchboardSettingsForm
            {
                return $this->formForTests;
            }
        };
    }

    public function testUnknownVerbShouldFailWithoutCallingThePluginAgain()
    {
        $plugin = $this->createMock(OASwitchboardPlugin::class);
        $plugin->expects($this->never())->method('manage');

        $result = $this->createManage($plugin)->execute([], $this->createRequest(['verb' => 'unknown']));

        $this->assertFalse($result->getStatus());
    }

    public function testSaveShouldBeRefusedBeforeAnyApiCallWhenTheSecretIsMissing()
    {
        $form = $this->createMock(OASwitchboardSettingsForm::class);
        $form->method('canBeSaved')->willReturn(false);
        $form->method('fetch')->willReturn('missing secret warning');
        $form->expects($this->never())->method('readInputData');
        $form->expects($this->never())->method('validateAPICredentials');
        $form->expects($this->never())->method('execute');

        $result = $this->createManage($this->createMock(OASwitchboardPlugin::class), $form)
            ->execute([], $this->createRequest(['verb' => 'settings', 'save' => '1']));

        $this->assertTrue($result->getStatus());
        $this->assertSame('missing secret warning', $result->getContent());
    }
}
