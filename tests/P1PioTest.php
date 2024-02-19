<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.OASwitchboardForOJS.messages.P1Pio');
import('plugins.generic.OASwitchboardForOJS.tests.helpers.P1PioExpectedTestData');

class P1PioTest extends PKPTestCase
{
    use P1PioExpectedTestData;

    private $p1Pio;
    private $json;

    protected function setUp(): void
    {
        parent::setUp();
        $this->p1Pio = new P1Pio();
        $this->json = json_decode($this->p1Pio->getJson());
    }

    public function testP1PioJsonHeader()
    {
        $header = $this->json->header;
        $this->assertEquals('p1', $header->type);
        $this->assertEquals('v2', $header->version);
        $this->assertEquals($this->getExpectedToSendMessageObject(), $header->to);
        $this->assertEquals($this->getExpectedFromMessageObject(), $header->from);
        $this->assertEquals('0000-0001', $header->ref);
        $this->assertEquals("2024-04-01", $header->validity);
        $this->assertEquals(true, $header->persistent);
        $this->assertEquals(true, $header->pio);
    }

    public function testP1PioJsonData()
    {
        $data = $this->json->data;
        $this->assertEquals('VoR', $data->timing);
        $this->assertEquals($this->getExpectedAuthorsArray(), $data->authors);
        $this->assertEquals($this->getExpectedArticleObject(), $data->article);
        $this->assertEquals($this->getExpectedJournalArray(), $data->journal);
    }
}
