<?php

import('lib.pkp.tests.PKPTestCase');
import('classes.core.Request');
import('classes.journal.Journal');
import('plugins.generic.OASwitchboard.OASwitchboardPlugin');
import('plugins.generic.OASwitchboard.classes.settings.OASwitchboardSettingsForm');

class OASwitchboardManageTest extends PKPTestCase
{
    private function createRequest(array $userVars)
    {
        $context = new Journal();
        $context->setId(1);
        $request = $this->getMockBuilder(Request::class)
            ->setMethods(['getUserVar', 'getContext', 'getUser'])
            ->getMock();
        $request->method('getUserVar')
            ->willReturnCallback(function ($name) use ($userVars) {
                return isset($userVars[$name]) ? $userVars[$name] : null;
            });
        $request->method('getContext')->willReturn($context);
        return $request;
    }

    private function createManage($plugin, $form = null)
    {
        return new class ($plugin, $form) extends OASwitchboardManage {
            private $formForTests;

            public function __construct($plugin, $formForTests)
            {
                parent::__construct($plugin);
                $this->formForTests = $formForTests;
            }

            protected function createSettingsForm($contextId)
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
