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
                'affiliation' => (string)$affiliationName,
                'institutions' => [
                    [
                        'name' => (string)$affiliationName,
                        'ror' => (string)$author->getData('rorId')
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
        $doi = $publication->getData('pub-id::doi') ?
            self::DOI_BASE_URL . $publication->getData('pub-id::doi') :
            "";
        $articleData = [
            'title' => $articleTitle,
            'doi' => $doi,
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

    public function validateSubmissionHasMandatoryData(): array
    {
        $data = $this->getContent()['data'];
        $missingDataMessages = [];

        foreach ($data['authors'] as $key => $author) {
            if (!isset($author['lastName'])) {
                $missingDataMessages[] = "The family name name of an author must be present.";
            }
            if (!isset($author['affiliation'])) {
                $missingDataMessages[] = "Affiliation of an author must be set.";
            }
            $institution = $author['institutions'][0];
            if (!isset($institution['name'])) {
                $missingDataMessages[] = "Affiliation of an author must be set.";
            }
        }
        if (!isset($data['article']['doi'])) {
            $missingDataMessages[] = "The article must have a DOI associated.";
        }
        if (!isset($data['journal']['id'])) {
            $missingDataMessages[] = "The journal must have a ISSN or eISSN assigned.";
        }

        return $missingDataMessages;
    }
}
