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
            $lastNameRetrieved = $author->getLocalizedFamilyName();
            $lastName = gettype($lastNameRetrieved) == 'array' ?
                reset($lastNameRetrieved) : $lastNameRetrieved;
            $firstName = $author->getLocalizedGivenName();
            $affiliation = $author->getLocalizedAffiliation();
            $authorsData[] = ['lastName' => $lastName, 'firstName' => $firstName, 'affiliation' => $affiliation];
        }
        return $authorsData;
    }

    public function getContent(): array
    {
        return $this->getSampleP1Pio();
    }
}
