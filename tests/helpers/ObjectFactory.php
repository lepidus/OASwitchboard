<?php

namespace APP\plugins\generic\OASwitchboard\tests\helpers;

use APP\author\Author;
use APP\journal\Journal;
use APP\plugins\generic\OASwitchboard\classes\messages\P1Pio;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\affiliation\Affiliation;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\decision\Decision;
use PKP\doi\Doi;
use PKP\galley\Galley;
use PKP\site\Site;
use PKP\submissionFile\SubmissionFile;

class ObjectFactory
{
    public const AUTHOR_LOCALE = 'pt_BR';
    public const AFFILIATION_NAME = 'Lepidus Tecnologia';
    public const ROR_ID = 'https://ror.org/xxxxxxxxrecipient';

    public static function buildAffiliation(string $name, ?string $ror = null): Affiliation
    {
        $affiliation = new class () extends Affiliation {
            public function getDefaultLocale(): ?string
            {
                return ObjectFactory::AUTHOR_LOCALE;
            }
        };
        $affiliation->setName($name, self::AUTHOR_LOCALE);
        if ($ror !== null) {
            $affiliation->setRor($ror);
        }
        return $affiliation;
    }

    public static function createTestAuthors($publication): array
    {
        $firstAuthor = new Author();
        $firstAuthor->setId(123);
        $firstAuthor->setGivenName('Iris', self::AUTHOR_LOCALE);
        $firstAuthor->setFamilyName('Castanheiras', self::AUTHOR_LOCALE);
        $firstAuthor->setAffiliations([
            self::buildAffiliation(self::AFFILIATION_NAME, self::ROR_ID),
        ]);
        $firstAuthor->setData('publicationId', $publication->getId());
        $firstAuthor->setData('orcid', 'https://orcid.org/0000-0000-0000-0000');
        $firstAuthor->setData('email', 'castanheirasiris@lepidus.com.br');
        $firstAuthor->setData('seq', 0);

        $secondAuthor = new Author();
        $secondAuthor->setId(321);
        $secondAuthor->setGivenName('Yves', self::AUTHOR_LOCALE);
        $secondAuthor->setFamilyName('Amorim', self::AUTHOR_LOCALE);
        $secondAuthor->setAffiliations([
            self::buildAffiliation(self::AFFILIATION_NAME),
        ]);
        $secondAuthor->setData('publicationId', $publication->getId());
        $secondAuthor->setData('seq', 1);

        return [$firstAuthor, $secondAuthor];
    }

    public static function createMockedJournal($onlineIssn = null, $printIssn = null): Journal
    {
        $journal = new Journal();
        $journal->setId(1);
        $journal->setName('Middle Earth papers', 'en_US');
        if ($printIssn and $onlineIssn) {
            $journal->setData('onlineIssn', $onlineIssn);
            $journal->setData('printIssn', $printIssn);
        }

        $stubJournalDao = new class ($journal) {
            private Journal $journal;
            public function __construct(Journal $journal)
            {
                $this->journal = $journal;
            }
            public function getById($id)
            {
                return $this->journal;
            }
            public function getByPath($path)
            {
                return null;
            }
        };
        DAORegistry::registerDAO('JournalDAO', $stubJournalDao);

        self::registerStubSite();

        return $journal;
    }

    public static function registerStubSite(string $primaryLocale = self::AUTHOR_LOCALE): void
    {
        $site = new Site();
        $site->setPrimaryLocale($primaryLocale);
        Registry::set('site', $site);
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
        $publication->setData('licenseUrl', 'https://creativecommons.org/licenses/by-nc-nd/4.0/');

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
        $submission->setData('galleys', [$galley]);

        $submission->setData('dateSubmitted', '2021-01-01 00:00:00');
        $submission->setData('datePublished', '2021-03-01 00:00:00');

        $galley->setData('submissionId', $submission->getId());
        $galley->setData('submissionFileId', $submissionFile->getId());

        return $submission;
    }

    public static function createP1PioMock($submission): P1Pio
    {
        $decision = new Decision();
        $decision->setData('stageId', 3);
        $decision->setData('decision', Decision::ACCEPT);
        $decision->setData('dateDecided', '2021-02-01');

        return new class ($submission, [$decision]) extends P1Pio {
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
            public function getFundersData(): array
            {
                return [[
                    'name' => 'Universidade Federal de Santa Catarina',
                    'fundref' => 'http://dx.doi.org/10.13039/501100007082',
                ]];
            }
        };
    }
}
