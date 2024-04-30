<?php

namespace APP\plugins\generic\OASwitchboard\tests\helpers;

use PKP\tests\PKPTestCase;
use APP\journal\Journal;
use PKP\db\DAORegistry;
use APP\submission\Submission;
use APP\publication\Publication;
use APP\author\Author;

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

        $secondAuthor = new Author();
        $secondAuthor->setId(321);
        $secondAuthor->setGivenName('Yves', 'pt_BR');
        $secondAuthor->setFamilyName('Amorim', 'pt_BR');
        $secondAuthor->setAffiliation('Lepidus Tecnologia', 'pt_BR');

        $secondAuthor->setData('publicationId', $publication->getId());

        return [$firstAuthor, $secondAuthor];
    }

    public static function createMockedJournal(PKPTestCase $testClass, $issn = null)
    {
        $journal = new Journal();
        $journal->setId(rand());
        $journal->setName('Middle Earth papers', 'en_US');
        if ($issn) {
            $journal->setData('onlineIssn', $issn);
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
        $submission = new Submission();
        $submission->setId(rand());
        $submission->setData('contextId', $journal->getId());

        $publication = new Publication();
        $publication->setId(rand());
        $publication->setData('title', 'The International relations of Middle-Earth');
        $publication->setData('doiId', '00.0000/mearth.0000');

        $authors = ObjectFactory::createTestAuthors($publication);
        if ($hasPrimaryContactId) {
            $publication->setData('primaryContactId', $authors[1]->getId());
        }
        $publication->setData('authors', $authors);
        $publication->setData('submissionId', $submission->getId());
        $submission->setData('currentPublicationId', $publication->getId());
        $submission->setData('publications', [$publication]);
        $submission->setLicenseUrl('https://creativecommons.org/licenses/by-nc-nd/4.0/');

        return $submission;
    }
}
