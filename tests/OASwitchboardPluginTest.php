<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\core\Application;
use APP\core\PageRouter;
use APP\plugins\generic\OASwitchboard\OASwitchboardPlugin;
use PKP\core\Dispatcher;
use PKP\core\Registry;
use Illuminate\Support\Facades\Event;
use PKP\observers\events\PublicationPublished;
use PKP\tests\PKPTestCase;

class OASwitchboardPluginTest extends PKPTestCase
{
    protected function getMockedRegistryKeys(): array
    {
        return [...parent::getMockedRegistryKeys(), 'request'];
    }

    public function testShouldListenToPublicationsEvenWhenNotEnabledForTheRequestJournal()
    {
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

            public function addLocaleData(): void
            {
            }
        };
        $listenersBefore = count(Event::getListeners(PublicationPublished::class));

        $this->assertTrue($plugin->register('generic', 'plugins/generic/OASwitchboard'));

        $this->assertCount($listenersBefore + 1, Event::getListeners(PublicationPublished::class));
    }
}
