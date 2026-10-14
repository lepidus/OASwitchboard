<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\plugins\generic\OASwitchboard\OASwitchboardPlugin;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PKP\observers\events\PublicationPublished;
use PKP\tests\PKPTestCase;

class OASwitchboardPluginTest extends PKPTestCase
{
    #[RunInSeparateProcess]
    public function testShouldListenToPublicationsEvenWhenNotEnabledForTheRequestJournal()
    {
        $this->mockRequest();
        $plugin = new class () extends OASwitchboardPlugin {
            public function getEnabled($contextId = null)
            {
                return false;
            }
        };
        $listenersBefore = count(Event::getListeners(PublicationPublished::class));

        $this->assertTrue($plugin->register('generic', 'plugins/generic/OASwitchboard'));

        $this->assertCount($listenersBefore + 1, Event::getListeners(PublicationPublished::class));
    }
}
