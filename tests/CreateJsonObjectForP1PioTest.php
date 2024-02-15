<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.OASwitchboardForOJS.messages.P1Pio');
import('plugins.generic.OASwitchboardForOJS.tests.factories.P1PioArrayGenerator');

class CreateJsonObjectForP1PioTest extends PKPTestCase
{
    use P1PioArrayGenerator;

    public function testCreateJSONObjectForP1PIO()
    {
        $p1Pio = new P1Pio();
        $json = json_decode($p1Pio->getMessage());
        $this->assertEquals('p1', $json->header->type);
        $this->assertEquals('v2', $json->header->version);
        $this->assertEquals($this->getToSendMessageObject(), $json->header->to);
        $this->assertEquals($this->getFromMessageObject(), $json->header->from);
        $this->assertEquals('0000-0001', $json->header->ref);
        $this->assertEquals("2024-04-01", $json->header->validity);
        $this->assertEquals(true, $json->header->persistent);
        $this->assertEquals(true, $json->header->pio);
        $this->assertEquals('VoR', $json->data->timing);
        $this->assertEquals($this->getAuthorsArray(), $json->data->authors);
        $this->assertEquals($this->getArticleObject(), $json->data->article);
        $this->assertEquals($this->getJournalArray(), $json->data->journal);
    }
}
