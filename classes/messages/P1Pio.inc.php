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
    private const REVIEW_STAGE_DECISION = 3;

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
        $listingOrder = 0;
        foreach ($authors as $author) {
            $listingOrder++;
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
                        'name' => (string)$affiliationName
                    ]
                ],
            ];

            $lastAuthorIndex = count($authorsData) - 1;
            if ($author->getData('rorId')) {
                $authorsData[$lastAuthorIndex]['institutions'][0]['ror'] = (string)$author->getData('rorId');
            }

            if (!empty($orcid)) {
                $authorsData[$lastAuthorIndex]['orcid'] = $orcid;
            }

            if (!empty($email)) {
                $authorsData[$lastAuthorIndex]['email'] = $email;
            }

            $primaryContactId = $this->submission->getCurrentPublication()->getData('primaryContactId');
            $authorsData[$lastAuthorIndex]['isCorrespondingAuthor'] = $primaryContactId === $author->getId();

            $authorsData[$lastAuthorIndex]['listingorder'] = $listingOrder;
        }
        return $authorsData;
    }

    public function getArticleData(): array
    {
        $articleTitle = $this->submission->getLocalizedFullTitle();
        $publication = $this->submission->getCurrentPublication();
        $license =  (string) $publication->getData('licenseUrl');
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
            ],
            'submissionId' => (string) $this->submission->getId(),
            'manuscript' => [
                'dates' => [
                    'submission' => (string) date('Y-m-d', strtotime($this->submission->getDateSubmitted())),
                    'acceptance' => (string) $this->getAcceptanceDate(),
                    'publication' => (string) date('Y-m-d', strtotime($this->submission->getDatePublished()))
                ]
            ]

        ];
        $fileId = $this->getFileId();
        if ($fileId) {
            $articleData['manuscript']['id'] = (string) $fileId;
        }

        $funders = $this->getFundersData();
        if (!empty($funders)) {
            $articleData['funders'] = $funders;
        }
        return $articleData;
    }

    public function getFundersData(): array
    {
        $fundersData = [];
        $fundingPlugin = PluginRegistry::getPlugin('generic', 'FundingPlugin');
        if (!is_null($fundingPlugin)) {
            if ($fundingPlugin->getEnabled()) {
                $funderDao = DAORegistry::getDAO('FunderDAO');
                $funders = $funderDao->getBySubmissionId($this->submission->getId());
                while ($funder = $funders->next()) {
                    $fundersData[] = [
                        'name' => (string) $funder->getFunderName(),
                        'fundref' => (string) $funder->getFunderIdentification()
                    ];
                }
            }
        }
        return $fundersData;
    }

    private function getAcceptanceDate(): ?string
    {
        $dateDecided = $this->getSubmissionDateDecided();

        return !is_null($dateDecided) ? date('Y-m-d', strtotime($dateDecided)) : null;
    }

    public function getSubmissionDateDecided(): ?string
    {
        $editDecisionDao = DAORegistry::getDAO('EditDecisionDAO');
        $decisions = $editDecisionDao->getEditorDecisions($this->submission->getId());

        foreach ($decisions as $decision) {
            if ($decision['stageId'] == self::REVIEW_STAGE_DECISION && $decision['decision'] == SUBMISSION_EDITOR_DECISION_ACCEPT) {
                return $decision['dateDecided'];
            }
        }

        return null;
    }

    private function getFileId()
    {
        $journal = DAORegistry::getDAO('JournalDAO')->getById($this->submission->getData('contextId'));
        $galleys = $this->getArticleTextGalleys();

        if (count($galleys) > 1) {
            return $this->getFirstPrimaryLocaleGalleyFileId($galleys, $journal->getPrimaryLocale());
        }

        return empty($galleys) ? null : $galleys[0]->getData('submissionFileId');
    }

    private function getFirstPrimaryLocaleGalleyFileId($galleys, $primaryLocale)
    {
        foreach ($galleys as $galley) {
            if ($galley->getData('locale') === $primaryLocale) {
                return $galley->getData('submissionFileId');
            }
        }
    }

    private function getArticleTextGenreId(): int
    {
        return DAORegistry::getDAO('GenreDAO')
            ->getByKey('SUBMISSION', $this->submission->getData('contextId'))
            ->getId();
    }

    private function getArticleTextGalleys(): array
    {
        $articleTextGalleys = [];
        foreach ($this->submission->getGalleys() as $galley) {
            $submissionFileId = $galley->getData('submissionFileId');
            $genreId = $submissionFileId ? $this->getGenreIdOfSubmissionFile($submissionFileId) : null;
            if ($genreId === $this->getArticleTextGenreId()) {
                $articleTextGalleys[] = $galley;
            }
        }
        return $articleTextGalleys;
    }

    public function getGenreIdOfSubmissionFile($submissionFileId)
    {
        return Services::get('submissionFile')->get($submissionFileId)->getData('genreId');
    }

    public function getJournalData(): array
    {
        $journalDao = DAORegistry::getDAO('JournalDAO');
        $journalId = $this->submission->getData('contextId');
        $journal = $journalDao->getById($journalId);

        $journalData = [
            'name' => (string) $journal->getLocalizedName(),
            'id' => (string) $this->chooseIssn($journal)
        ];

        if (!empty($journal->getData('onlineIssn'))) {
            $journalData['eissn'] = (string) $journal->getData('onlineIssn');
        }
        if (!empty($journal->getData('printIssn'))) {
            $journalData['issn'] = (string) $journal->getData('printIssn');
        }

        return $journalData;
    }

    private function chooseIssn($journal): string
    {
        return $journal->getData('onlineIssn') ?: $journal->getData('printIssn') ?: '';
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

        $missingDataMessages = array_unique($missingDataMessages);

        return $missingDataMessages;
    }
}
