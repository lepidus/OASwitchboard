<?php

trait P1PioData
{
    public function getP1PioData()
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
                "authors" => [
                    [
                        "listingorder" => 1,
                        "listingorderAtAcceptance" => 1,
                        "listingorderAtSubmission" => 1,
                        "lastName" => "Baggins",
                        "firstName" => "Frodo",
                        "initials" => "FB",
                        "ORCID" => "0000-0000-0000-0000",
                        "creditroles" => ["writing", "visualization"],
                        "isCorrespondingAuthor" => true,
                        "isCorrespondingAuthorAtAcceptance" => true,
                        "isCorrespondingAuthorAtSubmission" => true,
                        "collaboration" => "Laboratory collaboration",
                        "institutions" => [
                            [
                                "sourceaffiliation" => "University of Amsterdam, Department Computer Science",
                                "name" => "University of Amsterdam",
                                "ror" => "https://ror.org/04dkp9463",
                                "isni" => "0000 0000 8499 2262",
                                "country" => "NL"
                            ]
                        ],
                        "currentaddress" => [
                            [
                                "name" => "University of Amsterdam",
                                "ror" => "https://ror.org/04dkp9463",
                                "isni" => "0000 0000 8499 2262"
                            ]
                        ],
                        "affiliation" => "University of Amsterdam"
                    ]
                ],
                "article" => [
                    "title" => "The International relations of Middle-Earth",
                    "doi" => "https://doi.org/00.0000/mearth.0000",
                    "submissionId" => "00.0000",
                    "type" => "research-article",
                    "funders" => [
                        [
                            "name" => "Aragorn Foundation",
                            "ror" => "https://ror.org/999999",
                            "fundref" => "501100000000"
                        ],
                        [
                            "name" => "Middle-Earth Thinktank",
                            "ror" => "https://ror.org/888888",
                            "fundref" => "501100000001"
                        ]
                    ],
                    "acknowledgement" => "Aragorn Foundation, Middle-Earth Thinktank",
                    "grants" => [
                        [
                            "name" => "Generous grant",
                            "id" => "GD-000-001",
                            "doi" => "https://doi.org/00.0000/00-2020-000000"
                        ]
                    ],
                    "manuscript" => [
                        "dates" => [
                            "submission" => "2021-02-01",
                            "acceptance" => "2021-03-01",
                            "publication" => "2021-04-01"
                        ],
                        "id" => "00.0000-000"
                    ],
                    "preprint" => [
                        "title" => "The International relations of Middle-Earth",
                        "url" => "https://arxiv.org/00.0000-000",
                        "id" => "00.0000-000"
                    ],
                    "vor" => [
                        "publication" => "pure OA journal",
                        "license" => "CC BY",
                        "deposition" => "open repository, like PMC",
                        "researchdata" => "data available on request",
                        "startdate" => "2024-01-01"
                    ]
                ],
                "journal" => [
                    "name" => "Middle Earth papers",
                    "id" => "0000-0001",
                    "issn" => "0000-0001",
                    "eissn" => "0000-0002",
                    "inDOAJ" => true,
                    "typecomment" => "Diamond"
                ]
            ]
        ];
    }
}
