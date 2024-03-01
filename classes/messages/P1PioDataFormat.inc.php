<?php

trait P1PioDataFormat
{
    public function assembleMessage()
    {
        return [
            "header" => [
                "type" => "p1",
                "version" => "v2",
                "to" => [
                    "address" => $this->getRecipientAddress(),
                ],
                "persistent" => true,
                "pio" => true
            ],
            "data" => [
                "timing" => "VoR",
                "authors" => $this->getAuthorsData(),
                "article" => $this->getArticleData(),
                "journal" => $this->getJournalData()
            ]
        ];
    }
}
