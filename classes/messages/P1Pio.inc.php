<?php

import('plugins.generic.OASwitchboard.classes.messages.P1PioDataFormat');
import('plugins.generic.OASwitchboard.classes.messages.LicenseAcronym');
import('plugins.generic.OASwitchboard.classes.exceptions.P1PioException');
import('classes.submission.Submission');
import('lib.pkp.classes.log.SubmissionLog');

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
        $minimumData = $this->validateHasMinimumSubmissionData();
        if (!empty($minimumData)) {
            throw new P1PioException(__('plugins.generic.OASwitchboard.postRequirementsError'), 0, $minimumData);
        }
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
            $orcid = $author->getOrcid();
            $email = $author->getEmail();

            $authorsData[] = [
                'lastName' => $lastName,
                'firstName' => $firstName,
                'affiliation' => (string)$affiliationName,
                'institutions' => [
                    [
                        'name' => (string)$affiliationName,
                        'ror' => (string)$author->getData('rorId')
                    ]
                ],
            ];

            $lastAuthorIndex = count($authorsData) - 1;
            if (!empty($orcid)) {
                $authorsData[$lastAuthorIndex]['orcid'] = $orcid;
            }

            if (!empty($email)) {
                $authorsData[$lastAuthorIndex]['email'] = $email;
            }

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

    public function validateHasMinimumSubmissionData(): array
    {
        $missingDataMessages = [];

        $message = $this->getContent();
        $header = $message['header'];
        $data = $message['data'];

        foreach ($data['authors'] as $key => $author) {
            if (empty($author['lastName'])) {
                $missingDataMessages[] = 'plugins.generic.OASwitchboard.postRequirementsError.familyName';
            }
            if (empty($author['affiliation'])) {
                $missingDataMessages[] = 'plugins.generic.OASwitchboard.postRequirementsError.affiliation';
            }
        }
        if (empty($data['article']['doi'])) {
            $missingDataMessages[] = 'plugins.generic.OASwitchboard.postRequirementsError.doi';
        }
        if (empty($data['journal']['id'])) {
            $missingDataMessages[] = 'plugins.generic.OASwitchboard.postRequirementsError.issn';
        }

        return $missingDataMessages;
    }
}
