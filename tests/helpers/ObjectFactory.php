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
use PKP\facades\Locale;
use APP\core\Application;
use APP\plugins\generic\OASwitchboard\classes\messages\P1Pio;

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
        $journal->setId(rand());
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
        $galley->setLocale(Locale::getPrimaryLocale());

        $submissionFile = new SubmissionFile();
        $submissionFile->setId(9999);

        $genreDao = DAORegistry::getDAO('GenreDAO');
        $articleTextGenreId = $genreDao->getByKey('SUBMISSION')->getId();
        $submissionFile->setData('genreId', $articleTextGenreId);
        $submissionFile->setData('mimetype', 'application/pdf');
        $submissionFile->setData('submissionId', 456);
        $submissionFile->setData('assocType', Application::ASSOC_TYPE_SUBMISSION_FILE);
        $submissionFile->setData('assocId', $galley->getId());
        $submissionFile->setData('uploaderUserId', 1);
        $submissionFile->setData('createdAt', '2021-01-01 00:00:00');
        $submissionFile->setData('fileStage', SubmissionFile::SUBMISSION_FILE_DEPENDENT);
        $submissionFile->setData('fileId', 1234);

        $submission = new Submission();
        $submission->setId(456);
        $submission->setData('contextId', $journal->getId());

        $publication = new Publication();
        $publication->setId(rand());
        $publication->setData('title', 'The International relations of Middle-Earth');
        $publication->setData('doiId', '00.0000/mearth.0000');
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

        $galley->setData('submissionId', $submission->getId());
        $galley->setData('submissionFileId', $submissionFile->getId());

        return $submission;
    }

    public static function createP1PioMock(PKPTestCase $testClass, $submission)
    {
        $P1PioMock = $testClass->getMockBuilder(P1Pio::class)
            ->setConstructorArgs([$submission])
            ->setMethods(['getGenreOfSubmissionFile'])
            ->getMock();

        $P1PioMock->expects($testClass->any())
            ->method('getGenreOfSubmissionFile')
            ->will($testClass->returnValue(1));

        return $P1PioMock;
    }
}
