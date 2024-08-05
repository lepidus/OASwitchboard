<?php

import('lib.pkp.tests.PKPTestCase');

class ObjectFactory
{
    public static function createTestAuthors($publication): array
    {
        import('classes.article.Author');
        $firstAuthor = new Author();
        $firstAuthor->setId(123);
        $firstAuthor->setGivenName('Iris', 'pt_BR');
        $firstAuthor->setFamilyName('Castanheiras', 'pt_BR');
        $firstAuthor->setAffiliation('Lepidus Tecnologia', 'pt_BR');

        $firstAuthor->setData('publicationId', $publication->getId());
        $firstAuthor->setData('rorId', 'https://ror.org/xxxxxxxxrecipient');
        $firstAuthor->setOrcid('https://orcid.org/0000-0000-0000-0000');
        $firstAuthor->setEmail('castanheirasiris@lepidus.com.br');
        $firstAuthor->setData('isCorrespondingAuthor', false);
        $firstAuthor->setData('seq', 0);

        $secondAuthor = new Author();
        $secondAuthor->setId(321);
        $secondAuthor->setGivenName('Yves', 'pt_BR');
        $secondAuthor->setFamilyName('Amorim', 'pt_BR');
        $secondAuthor->setAffiliation('Lepidus Tecnologia', 'pt_BR');

        $secondAuthor->setData('publicationId', $publication->getId());
        $secondAuthor->setData('isCorrespondingAuthor', true);
        $secondAuthor->setData('seq', 1);

        return [$firstAuthor, $secondAuthor];
    }

    public static function createMockedJournal(PKPTestCase $testClass, $onlineIssn = null, $printIssn = null)
    {
        import('classes.journal.Journal');
        $journal = new Journal();
        $journal->setId(1);
        $journal->setName('Middle Earth papers', 'en_US');
        if ($printIssn && $onlineIssn) {
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
        import('classes.article.ArticleGalley');
        $galley = new ArticleGalley();
        $galley->setId(rand());
        $galley->setData('label', 'PDF');
        $galley->setLocale($journal->getPrimaryLocale());

        import('lib.pkp.classes.submission.SubmissionFile');
        $submissionFile = new SubmissionFile();
        $submissionFile->setId(9999);

        import('classes.submission.Submission');
        $submission = new Submission();
        $submission->setId(rand());
        $submission->setData('contextId', $journal->getId());

        import('classes.publication.Publication');
        $publication = new Publication();
        $publication->setId(rand());
        $publication->setData('title', 'The International relations of Middle-Earth');
        $publication->setData('pub-id::doi', '00.0000/mearth.0000');

        $authors = ObjectFactory::createTestAuthors($publication);
        if ($hasPrimaryContactId) {
            $publication->setData('primaryContactId', $authors[1]->getId());
        }
        $publication->setData('authors', $authors);
        $publication->setData('submissionId', $submission->getId());
        $submission->setData('currentPublicationId', $publication->getId());
        $submission->setData('publications', [$publication]);
        $submission->setData('galleys', [$galley]);
        $submission->setLicenseUrl('https://creativecommons.org/licenses/by-nc-nd/4.0/');

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

        return $P1PioMock;
    }

}
