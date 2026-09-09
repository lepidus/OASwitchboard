<?php

namespace APP\plugins\generic\OASwitchboard\tests\helpers;

use APP\plugins\generic\OASwitchboard\OASwitchboardPlugin;

trait CreatesPluginMocks
{
    private function createPluginMock(array $settings): OASwitchboardPlugin
    {
        $plugin = $this->createMock(OASwitchboardPlugin::class);
        $plugin->method('getSetting')
            ->willReturnCallback(fn ($contextId, $name) => $settings[$name] ?? null);
        return $plugin;
    }

    private function createConfiguredPluginMock(): OASwitchboardPlugin
    {
        return $this->createPluginMock(['username' => 'user@example.com', 'password' => 'encrypted']);
    }
}
