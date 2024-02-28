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
            $lastName = is_array($lastNameRetrieved) ? reset($lastNameRetrieved) : $lastNameRetrieved;
            $firstName = $author->getLocalizedGivenName();
            $affiliationName = $author->getLocalizedAffiliation();

            $authorsData[] = [
                'lastName' => $lastName,
                'firstName' => $firstName,
                'affiliation' => $affiliationName,
                'institutions' => [
                    [
                        'name' => $affiliationName
                    ]
                ]
            ];
        }
        return $authorsData;
    }

    public function getContent(): array
    {
        return $this->getSampleP1Pio();
    }
}
