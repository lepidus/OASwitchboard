<?php

import('plugins.generic.OASwitchboard.classes.messages.P1PioDataFormat');
import('plugins.generic.OASwitchboard.classes.messages.LicenseAcronym');
import('plugins.generic.OASwitchboard.classes.exceptions.P1PioException');
import('classes.submission.Submission');
import('lib.pkp.classes.log.SubmissionLog');
import('classes.workflow.EditorDecisionActionsManager');

class P1Pio
{
    use P1PioDataFormat;
    use LicenseAcronym;
    private $submission;
    private const ARTICLE_TYPE = 'research-article';
    private const DOI_BASE_URL = 'https://doi.org/';
    private const OPEN_ACCESS_POLICY = 'pure OA journal';
    public const REQUIREMENT_DOI = 'plugins.generic.OASwitchboard.postRequirementsError.doi';

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
                    'submission' => $this->formatDate($this->submission->getDateSubmitted()),
                    'acceptance' => $this->formatDate($this->getSubmissionDateDecided()),
                    'publication' => $this->formatDate($this->submission->getDatePublished())
                ]
            ]

        ];
        $fileId = $this->getFileId();
        if ($fileId) {
            $articleData['manuscript']['id'] = (string) $fileId;
        }

        $funders = $this->getSubmissionFunders();
        $fundersData = $this->getFundersData($funders);
        if (!empty($fundersData)) {
            $articleData['funders'] = $fundersData;
        }

        $grantsData = $this->getGrantsData($funders);
        if (!empty($grantsData)) {
            $articleData['grants'] = $grantsData;
        }
        return $articleData;
    }

    private function getFundersData(array $funders): array
    {
        $fundersData = [];
        foreach ($funders as $funder) {
            $fundersData[] = [
                'name' => trim((string) $funder->getFunderName()),
                'fundref' => trim((string) $funder->getFunderIdentification())
            ];
        }
        return $fundersData;
    }

    private function getGrantsData(array $funders): array
    {
        if (empty($funders)) {
            return [];
        }

        $awardNumbers = [];
        $funderAwardDao = DAORegistry::getDAO('FunderAwardDAO');
        foreach ($funders as $funder) {
            foreach ($funderAwardDao->getFunderAwardNumbersByFunderId($funder->getId()) as $awardNumber) {
                $awardNumber = trim((string) $awardNumber);
                if ($awardNumber !== '') {
                    $awardNumbers[$awardNumber] = ['id' => $awardNumber];
                }
            }
        }
        return array_values($awardNumbers);
    }

    private function getSubmissionFunders(): array
    {
        $contextId = (int) $this->submission->getData('contextId');
        $fundingPlugin = PluginRegistry::getPlugin('generic', 'FundingPlugin');
        if (is_null($fundingPlugin) || !$fundingPlugin->getEnabled($contextId)) {
            return [];
        }

        if (!$this->registerMissingFundingDaos()) {
            return [];
        }

        $submissionFunders = [];
        $funders = DAORegistry::getDAO('FunderDAO')->getBySubmissionId($this->submission->getId());
        while ($funder = $funders->next()) {
            if (trim((string) $funder->getFunderName()) !== '' || trim((string) $funder->getFunderIdentification()) !== '') {
                $submissionFunders[] = $funder;
            }
        }
        return $submissionFunders;
    }

    private function registerMissingFundingDaos(): bool
    {
        $registeredDaos = DAORegistry::getDAOs();
        $missingDaos = [];
        foreach (['FunderDAO', 'FunderAwardDAO'] as $daoName) {
            if (isset($registeredDaos[$daoName])) {
                continue;
            }
            $missingDaos[$daoName] = $this->createFundingDao($daoName);
            if (is_null($missingDaos[$daoName])) {
                return false;
            }
        }

        foreach ($missingDaos as $daoName => $dao) {
            DAORegistry::registerDAO($daoName, $dao);
        }
        return true;
    }

    protected function createFundingDao(string $daoName)
    {
        $daoFile = Core::getBaseDir() . '/plugins/generic/funding/classes/' . $daoName . '.inc.php';
        if (!class_exists($daoName, false) && is_file($daoFile)) {
            require_once $daoFile;
        }
        return class_exists($daoName, false) ? new $daoName() : null;
    }

    private function formatDate($date): string
    {
        return empty($date) ? '' : date('Y-m-d', strtotime($date));
    }

    public function getSubmissionDateDecided(): ?string
    {
        $latestAcceptance = null;
        foreach ($this->getSubmissionDecisions() as $decision) {
            if ($decision['decision'] != SUBMISSION_EDITOR_DECISION_ACCEPT) {
                continue;
            }
            if (is_null($latestAcceptance) || strtotime($decision['dateDecided']) >= strtotime($latestAcceptance)) {
                $latestAcceptance = $decision['dateDecided'];
            }
        }
        return $latestAcceptance;
    }

    public function getSubmissionDecisions(): array
    {
        return DAORegistry::getDAO('EditDecisionDAO')->getEditorDecisions($this->submission->getId());
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

    private function getArticleTextGenreId(): ?int
    {
        $genre = DAORegistry::getDAO('GenreDAO')->getByKey('SUBMISSION', $this->submission->getData('contextId'));
        return $genre ? (int) $genre->getId() : null;
    }

    private function getArticleTextGalleys(): array
    {
        $articleTextGenreId = $this->getArticleTextGenreId();
        if (is_null($articleTextGenreId)) {
            return [];
        }

        $articleTextGalleys = [];
        foreach ($this->submission->getGalleys() as $galley) {
            $submissionFileId = $galley->getData('submissionFileId');
            $genreId = $submissionFileId ? $this->getGenreIdOfSubmissionFile($submissionFileId) : null;
            if ($genreId === $articleTextGenreId) {
                $articleTextGalleys[] = $galley;
            }
        }
        return $articleTextGalleys;
    }

    public function getGenreIdOfSubmissionFile($submissionFileId)
    {
        $submissionFile = Services::get('submissionFile')->get($submissionFileId);
        return $submissionFile ? $submissionFile->getData('genreId') : null;
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

        $data = $this->getContent()['data'];

        foreach ($data['authors'] as $author) {
            if (empty($author['lastName'])) {
                $missingDataMessages[] = 'plugins.generic.OASwitchboard.postRequirementsError.familyName';
            }
            if (empty($author['affiliation'])) {
                $missingDataMessages[] = 'plugins.generic.OASwitchboard.postRequirementsError.affiliation';
            }
        }
        if (empty($data['article']['doi'])) {
            $missingDataMessages[] = self::REQUIREMENT_DOI;
        }
        if (empty($data['journal']['id'])) {
            $missingDataMessages[] = 'plugins.generic.OASwitchboard.postRequirementsError.issn';
        }

        $missingDataMessages = array_unique($missingDataMessages);

        return $missingDataMessages;
    }
}
