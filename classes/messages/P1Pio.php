<?php

namespace APP\plugins\generic\OASwitchboard\classes\messages;

use APP\facades\Repo;
use APP\plugins\generic\OASwitchboard\classes\exceptions\P1PioException;
use APP\submission\Submission;
use PKP\db\DAORegistry;
use PKP\decision\Decision;
use PKP\plugins\PluginRegistry;

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
        $publication = $this->submission->getCurrentPublication();
        $authors = $publication->getData('authors');
        $primaryContactId = $publication->getData('primaryContactId');

        $authorsData = [];
        $listingOrder = 0;
        foreach ($authors as $author) {
            $listingOrder++;
            $lastNameRetrieved = $author->getLocalizedFamilyName();
            $lastName = is_array($lastNameRetrieved) ? reset($lastNameRetrieved) : $lastNameRetrieved;
            $firstName = $author->getLocalizedGivenName();

            $institutions = [];
            foreach ($author->getAffiliations() as $affiliation) {
                $name = (string) $affiliation->getLocalizedName();
                if ($name === '') {
                    continue;
                }
                $institution = ['name' => $name];
                $ror = $affiliation->getRor();
                if (!empty($ror)) {
                    $institution['ror'] = (string) $ror;
                }
                $institutions[] = $institution;
            }

            $authorEntry = [
                'lastName' => $lastName,
                'firstName' => $firstName,
                'affiliation' => $institutions[0]['name'] ?? '',
                'institutions' => $institutions,
            ];

            $orcid = $author->getOrcid();
            if (!empty($orcid)) {
                $authorEntry['orcid'] = $orcid;
            }

            $authorEntry['isCorrespondingAuthor'] = $primaryContactId === $author->getId();
            $authorEntry['listingorder'] = $listingOrder;

            $authorsData[] = $authorEntry;
        }
        return $authorsData;
    }

    public function getArticleData(): array
    {
        $publication = $this->submission->getCurrentPublication();
        $articleTitle = $publication->getLocalizedFullTitle();
        $license = $publication->getData('licenseUrl');
        $licenseAcronym = $this->getLicenseAcronym($license);
        $doi = $publication->getDoi() ?
            self::DOI_BASE_URL . $publication->getDoi() :
            '';

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
                    'submission' => $this->formatDate($this->submission->getData('dateSubmitted')),
                    'acceptance' => $this->getAcceptanceDate(),
                    'publication' => $this->formatDate($publication->getData('datePublished'))
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

    /**
     * The Funding plugin only registers its DAOs when enabled for the journal it was
     * loaded for, which a queue worker, loading plugins with no journal, never is.
     */
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

    protected function createFundingDao(string $daoName): ?object
    {
        $daoClass = 'APP\plugins\generic\funding\classes\\' . $daoName;
        return class_exists($daoClass) ? new $daoClass() : null;
    }

    private function formatDate(?string $date): string
    {
        return empty($date) ? '' : date('Y-m-d', strtotime($date));
    }

    private function getAcceptanceDate(): string
    {
        $latestAcceptance = null;
        foreach ($this->getSubmissionDecisions() as $decision) {
            if (!in_array($decision->getData('decision'), [Decision::ACCEPT, Decision::SKIP_EXTERNAL_REVIEW], true)) {
                continue;
            }
            if (is_null($latestAcceptance) || strtotime($decision->getData('dateDecided')) >= strtotime($latestAcceptance)) {
                $latestAcceptance = $decision->getData('dateDecided');
            }
        }
        return $this->formatDate($latestAcceptance);
    }

    public function getSubmissionDecisions(): array
    {
        return Repo::decision()->getCollector()
            ->filterBySubmissionIds([$this->submission->getId()])
            ->getMany()->toArray();
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
        return DAORegistry::getDAO('GenreDAO')
            ->getByKey('SUBMISSION', $this->submission->getData('contextId'))
            ?->getId();
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
        return Repo::submissionFile()->get($submissionFileId)?->getData('genreId');
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
