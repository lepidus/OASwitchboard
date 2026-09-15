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

    public static function createP1PioMock(PKPTestCase $testClass, $submission)
    {
        $P1PioMock = $testClass->getMockBuilder(P1Pio::class)
            ->setConstructorArgs([$submission])
            ->setMethods(['getGenreIdOfSubmissionFile', 'getSubmissionDecisions'])
            ->getMock();

        $P1PioMock->expects($testClass->any())
            ->method('getGenreIdOfSubmissionFile')
            ->will($testClass->returnValue(1));

        $decision = new Decision();
        $decision->setData('stageId', 3);
        $decision->setData('decision', Decision::ACCEPT);
        $decision->setData('dateDecided', '2021-02-01');

        $P1PioMock->expects($testClass->any())
            ->method('getSubmissionDecisions')
            ->will($testClass->returnValue([$decision]));
        return $P1PioMock;
    }

    /**
     * Registers a stand-in for the Funding plugin and its DAOs.
     *
     * @param array $funders Each entry has 'id', 'name', 'identification' and 'awardNumbers'
     */
    public static function registerStubFundingPlugin(array $funders, bool $enabled = true): void
    {
        $fundingPlugin = new class ($enabled) extends Plugin {
            private bool $enabled;
            public function __construct(bool $enabled)
            {
                parent::__construct();
                $this->enabled = $enabled;
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
            public function getEnabled()
            {
                return $this->enabled;
            }
        };
        $plugins = & PluginRegistry::getPlugins();
        $plugins['generic']['FundingPlugin'] = $fundingPlugin;

        DAORegistry::registerDAO('FunderDAO', new class ($funders) {
            private array $funders;
            public function __construct(array $funders)
            {
                $this->funders = $funders;
            }
            public function getBySubmissionId($submissionId)
            {
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
        });

        DAORegistry::registerDAO('FunderAwardDAO', new class ($funders) {
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
        });
    }
}
