<?php

import('plugins.generic.OASwitchboardForOJS.classes.messages.P1PioDataFormat');
import('plugins.generic.OASwitchboardForOJS.classes.messages.LicenseAcronym');
import('classes.submission.Submission');

class P1Pio
{
    use P1PioDataFormat;
    use LicenseAcronym;

    private $submission;
    private const ARTICLE_TYPE = 'research-article';
    private const DOI_BASE_URL = 'https://doi.org/';
    private const OPEN_ACCESS_POLICY = 'pure OA journal';

    public function __construct(Submission $submission)
    {
        $this->submission = $submission;
    }

    public function getRecipientAddress()
    {
        $authors = $this->submission->getAuthors();
        $firstAuthor = $authors[0];

        return $firstAuthor->getData('rorId');
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
                        'name' => $affiliationName,
                        'ror' => $author->getData('rorId')
                    ]
                ]
            ];
        }
        return $authorsData;
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
                'publication' => self::OPEN_ACCESS_POLICY,
                'license' => $licenseAcronym
            ]
        ];
        return $articleData;
    }

    public function getJournalData(): array
    {
        $journalDao = DAORegistry::getDAO('JournalDAO');
        $journalId = $this->submission->getContextId();
        $journal = $journalDao->getById($journalId);
        $issn = $this->retrieveIssn($journal);

        $journalData = [
            'name' => $journal->getLocalizedName(),
            'id' => $issn
        ];
        return $journalData;
    }

    private function retrieveIssn($journal)
    {
        if ($journal->getData('onlineIssn')) {
            return $journal->getData('onlineIssn');
        } elseif ($journal->getData('printIssn')) {
            return $journal->getData('printIssn');
        }

        return null;
    }

    public function getContent(): array
    {
        return $this->assembleMessage();
    }
}
