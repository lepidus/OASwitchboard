<?php

namespace APP\plugins\generic\OASwitchboard\classes\messages;

use APP\submission\Submission;
use APP\plugins\generic\OASwitchboard\classes\exceptions\P1PioException;
use PKP\db\DAORegistry;
use APP\facades\Repo;
use PKP\facades\Locale;
use PKP\decision\Decision;

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
        $authors = $this->submission->getCurrentPublication()->getData('authors');
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

            $orcid = $author->getOrcid();
            if (isset($orcid) && !empty($orcid)) {
                $authorsData[count($authorsData) - 1]['orcid'] = $orcid;
            }

            $email = $author->getEmail();
            if (isset($email) && !empty($email)) {
                $authorsData[count($authorsData) - 1]['email'] = $email;
            }

            $primaryContactId = $this->submission->getCurrentPublication()->getData('primaryContactId');
            $authorsData[count($authorsData) - 1]['isCorrespondingAuthor'] = $primaryContactId === $author->getId();

            $contributorSequence = $author->getData('seq') + 1;
            $authorsData[count($authorsData) - 1]['listingorder'] = $contributorSequence;
        }
        return $authorsData;
    }

    public function getArticleData(): array
    {
        $articleTitle = $this->submission->getLocalizedFullTitle();
        $publication = $this->submission->getCurrentPublication();
        $license = $this->submission->getLicenseUrl();
        $licenseAcronym = $this->getLicenseAcronym($license);
        $doi = $publication->getData('doiId') ?
            self::DOI_BASE_URL . $publication->getData('doiId') :
            "";

        $articleData = [
            'title' => $articleTitle,
            'doi' => (string) $doi,
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

        return $articleData;
    }

    private function getAcceptanceDate(): ?string
    {
        $editorialDecision = $this->getSubmissionDecisions();

        foreach ($editorialDecision as $decision) {
            if ($decision->getData('stageId') === 3 && $decision->getData('decision') === Decision::ACCEPT) {
                return date('Y-m-d', strtotime($decision->getData('dateDecided')));
            }
        }
        return null;
    }

    public function getSubmissionDecisions(): array
    {
        return Repo::decision()->getCollector()
            ->filterBySubmissionIds([$this->submission->getId()])
            ->getMany()->toArray();
    }

    private function getFileId()
    {
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $articleTextGenreId = $genreDao->getByKey('SUBMISSION')->getId();

        $galleys = $this->submission->getGalleys();

        $galleyFileId = [];

        foreach ($galleys as $galley) {
            $submissionFileId = $galley->getData('submissionFileId');
            $genreId = $this->getGenreOfSubmissionFile($submissionFileId);
            if ($genreId === $articleTextGenreId) {
                if (Locale::getPrimaryLocale() == $galley->getLocale()) {
                    $galleyFileId[] = $submissionFileId;
                }
            }
        }

        return isset($galleyFileId[0]) ? $galleyFileId[0] : null;
    }

    public function getGenreOfSubmissionFile($submissionFileId)
    {
        return Repo::submissionFile()->get($submissionFileId)->getData('genreId');
    }

    public function getJournalData(): array
    {
        $journalDao = DAORegistry::getDAO('JournalDAO');
        $journalId = $this->submission->getContextId();
        $journal = $journalDao->getById($journalId);

        $journalData = [
            'name' => (string) $journal->getLocalizedName(),
            'id' => (string) $this->chooseIssn($journal),
            'eissn' => (string) $journal->getData('onlineIssn'),
            'issn' => (string) $journal->getData('printIssn')
        ];
        return $journalData;
    }

    private function chooseIssn($journal)
    {
        return $journal->getData('onlineIssn') ?: $journal->getData('printIssn') ?: null;
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
