<?php

trait P1PioExpectedTestData
{
    private function getExpectedRecipient()
    {
        return [
            "address" => "https://ror.org/broadcast"
        ];
    }

    private function getExpectedAuthorsArray()
    {
        $authors = [
            [
                'lastName' => 'Castanheiras',
                'firstName' => 'Iris',
                'affiliation' => 'Lepidus Tecnologia',
                'institutions' => [
                    [
                        'name' => 'Lepidus Tecnologia',
                        'ror' => 'https://ror.org/xxxxxxxxrecipient'
                    ]
                ],
                'orcid' => 'https://orcid.org/0000-0000-0000-0000',
                'email' => 'castanheirasiris@lepidus.com.br',
                'isCorrespondingAuthor' => false,
                'listingorder' => 1
            ],
            [
                'lastName' => 'Amorim',
                'firstName' => 'Yves',
                'affiliation' => 'Lepidus Tecnologia',
                'institutions' => [
                    [
                        'name' => 'Lepidus Tecnologia',
                        'ror' => ''
                    ]
                ],
                'isCorrespondingAuthor' => true,
                'listingorder' => 2
            ]
        ];
        return $authors;
    }

    private function getExpectedArticleObject()
    {
        return [
                'title' => 'The International relations of Middle-Earth',
                'doi' => 'https://doi.org/00.0000/mearth.0000',
                'type' => 'research-article',
                'vor' => [
                    'publication' => 'pure OA journal',
                    'license' => 'CC BY-NC-ND'
                ],
                'submissionId' => $this->submission->getId(),
                'manuscript' => [
                    'id' => '9999',
                    'dates' => [
                        'submission' => '2021-01-01',
                        'acceptance' => '2021-01-20',
                        'publication' => '2021-03-01'
                    ]
                ],
                'funders' => [
                    [
                        'name' => "Universidade Federal de Santa Catarina",
                        'fundref' => 'http://dx.doi.org/10.13039/501100007082'
                    ]
                ]
            ];
    }

    private function getExpectedJournalArray()
    {
        $journal = [
            'name' => 'Middle Earth papers',
            'id' => '0000-0001',
            'eissn' => '0000-0001',
            'issn' => '0000-0002'
        ];
        return $journal;
    }
}
