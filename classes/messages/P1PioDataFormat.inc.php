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
                    "address" => "https://ror.org/xxxxxxxx",
                    "name" => "Science Publisher"
                ],
                "from" => [
                    "address" => "https://ror.org/04dkp9463",
                    "name" => "University of Amsterdam"
                ],
                "ref" => "0000-0001",
                "validity" => "2024-04-01",
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
