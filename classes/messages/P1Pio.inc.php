<?php

import('plugins.generic.OASwitchboardForOJS.classes.messages.P1PioDataFormat');

class P1Pio
{
    use P1PioDataFormat;
    private $authors;

    public function __construct(array $authors)
    {
        $this->authors = $authors;
    }

    public function getAuthorsData()
    {
        $authorsData = [];
        foreach ($this->authors as $author) {
            $authorsData[] = $author->getLocalizedGivenName();
            $authorsData[] = $author->getLocalizedFamilyName();
        }
        return $authorsData;
    }

    public function getContent(): array
    {
        return $this->getSampleP1Pio();
    }
}
