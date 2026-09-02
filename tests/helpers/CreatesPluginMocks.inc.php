<?php

import('plugins.generic.OASwitchboard.OASwitchboardPlugin');

trait CreatesPluginMocks
{
    private function createPluginMock(array $settings): OASwitchboardPlugin
    {
        $plugin = $this->createMock(OASwitchboardPlugin::class);
        $plugin->method('getSetting')
            ->willReturnCallback(function ($contextId, $name) use ($settings) {
                return isset($settings[$name]) ? $settings[$name] : null;
            });
        return $plugin;
    }

    private function createConfiguredPluginMock(): OASwitchboardPlugin
    {
        return $this->createPluginMock(['username' => 'user@example.com', 'password' => 'encrypted']);
    }
}
