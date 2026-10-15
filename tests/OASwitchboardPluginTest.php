<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.OASwitchboard.OASwitchboardPlugin');

class OASwitchboardPluginTest extends PKPTestCase
{
    protected function getMockedRegistryKeys()
    {
        return array_merge(parent::getMockedRegistryKeys(), ['request']);
    }

    public function testShouldHandlePublicationsEvenWhenNotEnabledForTheRequestJournal()
    {
        import('classes.core.PageRouter');
        import('lib.pkp.classes.core.Dispatcher');
        $_SERVER['PATH_INFO'] = 'index/test-page/test-op';
        Registry::delete('request');
        $router = new PageRouter();
        $router->setApplication(Application::get());
        $dispatcher = new Dispatcher();
        $dispatcher->setApplication(Application::get());
        $router->setDispatcher($dispatcher);
        Application::get()->getRequest()->setRouter($router);
        $plugin = new class () extends OASwitchboardPlugin {
            public function getEnabled($contextId = null)
            {
                return false;
            }

            public function addLocaleData($locale = null)
            {
            }
        };
        $hooks = & HookRegistry::getHooks();
        $hooksBefore = $hooks;

        try {
            $this->assertTrue($plugin->register('generic', 'plugins/generic/OASwitchboard'));
            $publishCallbacks = [];
            foreach (HookRegistry::getHooks('Publication::publish') ?: [] as $priorityHooks) {
                foreach ($priorityHooks as $callback) {
                    $publishCallbacks[] = $callback;
                }
            }
        } finally {
            $hooks = $hooksBefore;
        }

        $this->assertContains('sendToOASwitchboard', array_map(function ($callback) {
            return is_array($callback) ? $callback[1] : null;
        }, $publishCallbacks));
    }
}
