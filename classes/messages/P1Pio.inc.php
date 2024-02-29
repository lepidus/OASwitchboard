<?php

import('plugins.generic.OASwitchboardForOJS.classes.messages.P1PioDataFormat');
import('classes.submission.Submission');

class P1Pio
{
    use P1PioDataFormat;

    private $submission;
    private const ARTICLE_TYPE = 'research-article';
    private const DOI_BASE_URL = 'https://doi.org/';

    public function __construct(Submission $submission)
    {
        $this->submission = $submission;
    }

    private function getLicenseAcronym($licenseURL)
    {
        $licenseAcronymMap = array(
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-nc-nd/4.0[/]?|' => 'CC BY-NC-ND',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-nc/4.0[/]?|' => 'CC BY-NC',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-nc-sa/4.0[/]?|' => 'CC BY-NC-SA',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-nd/4.0[/]?|' => 'CC BY-ND',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by/4.0[/]?|' => 'CC BY',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-sa/4.0[/]?|' => 'CC BY-SA',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-nc-nd/3.0[/]?|' => 'CC BY-NC-ND',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-nc/3.0[/]?|' => 'CC BY-NC',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-nc-sa/3.0[/]?|' => 'CC BY-NC-SA',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-nd/3.0[/]?|' => 'CC BY-ND',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by/3.0[/]?|' => 'CC BY',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-sa/3.0[/]?|' => 'CC BY-SA',
            '|http[s]?://(www\.)?creativecommons.org/publicdomain/zero/1.0[/]?|' => 'CC0'
        );

        foreach ($licenseAcronymMap as $pattern => $acronym) {
            if (preg_match($pattern, $licenseURL ?? '')) {
                return $acronym;
            }
        }
        return null;
    }

    public function getArticleData(): array
    {
        $articleTitle = $this->submission->getLocalizedFullTitle();
        $publication = $this->submission->getCurrentPublication();
        $license = $this->submission->getLicenseUrl();
        $licenseAcronym = $this->getLicenseAcronym($license);
        $articleData = [
            'title' => $articleTitle,
            'doi' => self::DOI_BASE_URL . $publication->getData('pub-id::doi'),
            'type' => self::ARTICLE_TYPE,
            'vor' => [
                'publication' => 'pure OA journal',
                'license' => $licenseAcronym
            ]
        ];
        return $articleData;
    }

    public function getAuthorsData(): array
    {
        $authors = $this->submission->getAuthors();
        $authorsData = [];
        foreach ($authors as $author) {
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
        return $this->assembleMessage();
    }
}
