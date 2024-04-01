<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.OASwitchboard.classes.OASwitchboardService');

class OASwitchboardServiceTest extends PKPTestCase
{
    private $submission;

    protected function setUp(): void
    {
        parent::setUp();
        $journal = $this->createMockedJournal($issn = "0000-0001");
        $this->submission = $this->createTestSubmission($journal);
    }

    protected function getMockedDAOs()
    {
        return [
            'JournalDAO'
        ];
    }

    private function createTestAuthors($publication): array
    {
        import('classes.article.Author');
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

    private function createMockedJournal($issn = null)
    {
        import('classes.journal.Journal');
        $journal = new Journal();
        $journal->setId(rand());
        $journal->setName('Middle Earth papers', 'en_US');
        if ($issn) {
            $journal->setData('onlineIssn', $issn);
        }

        $mockJournalDAO = $this->getMockBuilder(JournalDAO::class)
            ->setMethods(['getById'])
            ->getMock();

        $mockJournalDAO->expects($this->any())
            ->method('getById')
            ->will($this->returnValue($journal));

        DAORegistry::registerDAO('JournalDAO', $mockJournalDAO);

        return $journal;
    }

    private function createTestSubmission($journal, $hasPrimaryContactId = false): Submission
    {
        import('classes.submission.Submission');
        $submission = new Submission();
        $submission->setId(rand());
        $submission->setData('contextId', $journal->getId());

        import('classes.publication.Publication');
        $publication = new Publication();
        $publication->setId(rand());
        $publication->setData('title', 'The International relations of Middle-Earth');
        $publication->setData('pub-id::doi', '00.0000/mearth.0000');

        $authors = $this->createTestAuthors($publication);
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

    public function testSubmissionAtLeastOneAuthorWithRorAssociated()
    {
        $this->assertTrue(OASwitchboardService::isRorAssociated($this->submission));
    }
}
