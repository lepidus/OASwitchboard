<?php

import('plugins.generic.OASwitchboard.OASwitchboardPlugin');

trait CreatesPluginMocks
{
    private function createPluginMock(array $settings, bool $enabled = true): OASwitchboardPlugin
    {
        $plugin = $this->createMock(OASwitchboardPlugin::class);
        $plugin->method('getSetting')
            ->willReturnCallback(function ($contextId, $name) use ($settings) {
                return isset($settings[$name]) ? $settings[$name] : null;
            });
        $plugin->method('getEnabled')->willReturn($enabled);
        return $plugin;
    }

    private function createConfiguredPluginMock(): OASwitchboardPlugin
    {
        return $this->createPluginMock(['username' => 'user@example.com', 'password' => 'encrypted']);
    }
}
