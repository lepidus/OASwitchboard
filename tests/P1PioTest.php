<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.OASwitchboardForOJS.classes.messages.P1Pio');
import('plugins.generic.OASwitchboardForOJS.tests.helpers.P1PioExpectedTestData');

class P1PioTest extends PKPTestCase
{
    use P1PioExpectedTestData;

    private $P1PioMessage;

    protected function setUp(): void
    {
        parent::setUp();
        $P1Pio = new P1Pio();
        $this->P1PioMessage = $P1Pio->getContent();
    }

    public function testP1PioMessageHeader()
    {
        $header = $this->P1PioMessage['header'];
        $this->assertEquals('p1', $header['type']);
        $this->assertEquals('v2', $header['version']);
        $this->assertEquals($this->getExpectedToSendMessageObject(), $header['to']);
        $this->assertEquals($this->getExpectedFromMessageObject(), $header['from']);
        $this->assertEquals('0000-0001', $header['ref']);
        $this->assertEquals("2024-04-01", $header['validity']);
        $this->assertEquals(true, $header['persistent']);
        $this->assertEquals(true, $header['pio']);
    }

    public function testP1PioMessageData()
    {
        $data = $this->P1PioMessage['data'];
        $this->assertEquals('VoR', $data['timing']);
        $this->assertEquals($this->getExpectedAuthorsArray(), $data['authors']);
        $this->assertEquals($this->getExpectedArticleObject(), $data['article']);
        $this->assertEquals($this->getExpectedJournalArray(), $data['journal']);
    }
}
