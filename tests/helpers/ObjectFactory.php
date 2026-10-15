<?php

namespace APP\plugins\generic\OASwitchboard\tests\helpers;

use PKP\tests\PKPTestCase;
use APP\journal\Journal;
use PKP\db\DAORegistry;
use APP\submission\Submission;
use APP\publication\Publication;
use APP\author\Author;
use PKP\galley\Galley;
use PKP\submissionFile\SubmissionFile;
use APP\core\Application;
use APP\plugins\generic\OASwitchboard\classes\messages\P1Pio;
use PKP\decision\Decision;
use PKP\doi\Doi;
use PKP\plugins\Plugin;
use PKP\plugins\PluginRegistry;

class ObjectFactory
{
    public static function createTestAuthors($publication): array
    {
        $firstAuthor = new Author();
        $firstAuthor->setId(123);
        $firstAuthor->setGivenName('Iris', 'pt_BR');
        $firstAuthor->setFamilyName('Castanheiras', 'pt_BR');
        $firstAuthor->setAffiliation('Lepidus Tecnologia', 'pt_BR');

        $firstAuthor->setData('publicationId', $publication->getId());
        $firstAuthor->setData('rorId', 'https://ror.org/xxxxxxxxrecipient');
        $firstAuthor->setData('orcid', 'https://orcid.org/0000-0000-0000-0000');
        $firstAuthor->setData('email', 'castanheirasiris@lepidus.com.br');
        $firstAuthor->setData('seq', 0);

        $secondAuthor = new Author();
        $secondAuthor->setId(321);
        $secondAuthor->setGivenName('Yves', 'pt_BR');
        $secondAuthor->setFamilyName('Amorim', 'pt_BR');
        $secondAuthor->setAffiliation('Lepidus Tecnologia', 'pt_BR');
        $secondAuthor->setData('seq', 1);

        $secondAuthor->setData('publicationId', $publication->getId());

        return [$firstAuthor, $secondAuthor];
    }

    public static function createMockedJournal(PKPTestCase $testClass, $onlineIssn = null, $printIssn = null)
    {
        $journal = new Journal();
        $journal->setId(1);
        $journal->setName('Middle Earth papers', 'en_US');
        if ($printIssn and $onlineIssn) {
            $journal->setData('onlineIssn', $onlineIssn);
            $journal->setData('printIssn', $printIssn);
        }

        $mockJournalDAO = $testClass->getMockBuilder(JournalDAO::class)
            ->setMethods(['getById'])
            ->getMock();

        $mockJournalDAO->expects($testClass->any())
            ->method('getById')
            ->will($testClass->returnValue($journal));

        DAORegistry::registerDAO('JournalDAO', $mockJournalDAO);

        return $journal;
    }

    public static function createTestSubmission($journal, $hasPrimaryContactId = false): Submission
    {
        $galley = new Galley();
        $galley->setId(rand());
        $galley->setData('label', 'PDF');
        $galley->setLocale($journal->getPrimaryLocale());

        $submissionFile = new SubmissionFile();
        $submissionFile->setId(9999);

        $submission = new Submission();
        $submission->setId(456);
        $submission->setData('contextId', $journal->getId());

        $publication = new Publication();
        $publication->setId(rand());
        $publication->setData('title', 'The International relations of Middle-Earth');

        $doiObject = new Doi();
        $doiObject->setData('doi', '00.0000/mearth.0000');
        $publication->setData('doiObject', $doiObject);

        $publication->setData('primaryContactId', 123);
        $authors = ObjectFactory::createTestAuthors($publication);
        if ($hasPrimaryContactId) {
            $publication->setData('primaryContactId', $authors[1]->getId());
        }
        $publication->setData('authors', $authors);
        $publication->setData('submissionId', $submission->getId());

        $submission->setData('currentPublicationId', $publication->getId());
        $submission->setData('publications', [$publication]);
        $submission->setLicenseUrl('https://creativecommons.org/licenses/by-nc-nd/4.0/');
        $submission->setData('galleys', [$galley]);

        $submission->setDateSubmitted('2021-01-01 00:00:00');
        $submission->setDatePublished('2021-03-01 00:00:00');

        $galley->setData('submissionId', $submission->getId());
        $galley->setData('submissionFileId', $submissionFile->getId());

        return $submission;
    }

    public static function buildDecision(int $decisionType, string $dateDecided, int $stageId = 3): Decision
    {
        $decision = new Decision();
        $decision->setData('stageId', $stageId);
        $decision->setData('decision', $decisionType);
        $decision->setData('dateDecided', $dateDecided);
        return $decision;
    }

    public static function createP1PioMock(PKPTestCase $testClass, $submission, ?array $decisions = null): P1Pio
    {
        $decisions ??= [self::buildDecision(Decision::ACCEPT, '2021-02-01')];

        return new class ($submission, $decisions) extends P1Pio {
            private array $stubDecisions;
            public function __construct($submission, array $stubDecisions)
            {
                $this->stubDecisions = $stubDecisions;
                parent::__construct($submission);
            }
            public function getGenreIdOfSubmissionFile($submissionFileId)
            {
                return 1;
            }
            public function getSubmissionDecisions(): array
            {
                return $this->stubDecisions;
            }
            protected function createFundingDao(string $daoName): ?object
            {
                return ObjectFactory::$creatableFundingDaos[$daoName] ?? null;
            }
        };
    }

    public static array $creatableFundingDaos = [];

    public static function registerStubFundingPlugin(array $funders, ?int $enabledContextId = 1): void
    {
        $fundingPlugin = new class ($enabledContextId) extends Plugin {
            private ?int $enabledContextId;
            public function __construct(?int $enabledContextId)
            {
                parent::__construct();
                $this->enabledContextId = $enabledContextId;
            }
            public function getName()
            {
                return 'FundingPlugin';
            }
            public function getDisplayName()
            {
                return 'Funding';
            }
            public function getDescription()
            {
                return 'Funding';
            }
            public function getEnabled($contextId = null)
            {
                return $contextId !== null && $contextId === $this->enabledContextId;
            }
        };
        $plugins = & PluginRegistry::getPlugins();
        $plugins['generic']['FundingPlugin'] = $fundingPlugin;

        foreach (self::createStubFundingDaos($funders) as $daoName => $dao) {
            DAORegistry::registerDAO($daoName, $dao);
        }
    }

    public static function createStubFundingDaos(array $funders): array
    {
        $funderDao = new class ($funders) {
            public int $calls = 0;
            private array $funders;
            public function __construct(array $funders)
            {
                $this->funders = $funders;
            }
            public function getBySubmissionId($submissionId)
            {
                $this->calls++;
                $funders = array_map(fn ($funder) => new class ($funder) {
                    private array $funder;
                    public function __construct(array $funder)
                    {
                        $this->funder = $funder;
                    }
                    public function getId()
                    {
                        return $this->funder['id'];
                    }
                    public function getFunderName()
                    {
                        return $this->funder['name'];
                    }
                    public function getFunderIdentification()
                    {
                        return $this->funder['identification'];
                    }
                }, $this->funders);

                return new class ($funders) {
                    private array $funders;
                    public function __construct(array $funders)
                    {
                        $this->funders = $funders;
                    }
                    public function next()
                    {
                        return array_shift($this->funders);
                    }
                };
            }
        };

        $funderAwardDao = new class ($funders) {
            private array $funders;
            public function __construct(array $funders)
            {
                $this->funders = $funders;
            }
            public function getFunderAwardNumbersByFunderId($funderId)
            {
                foreach ($this->funders as $funder) {
                    if ($funder['id'] === $funderId) {
                        return $funder['awardNumbers'];
                    }
                }
                return [];
            }
        };

        return ['FunderDAO' => $funderDao, 'FunderAwardDAO' => $funderAwardDao];
    }
}
