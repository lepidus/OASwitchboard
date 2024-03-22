<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.OASwitchboard.classes.messages.P1Pio');
import('plugins.generic.OASwitchboard.tests.helpers.P1PioExpectedTestData');

class P1PioTest extends PKPTestCase
{
    use P1PioExpectedTestData;

    private $P1Pio;
    private $submission;

    protected function setUp(): void
    {
        parent::setUp();
        $journal = $this->createMockedJournal($issn = "0000-0001");
        $this->submission = $this->createTestSubmission($journal);
        $this->P1Pio = new P1Pio($this->submission);
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
        $firstAuthor->setGivenName('Iris', 'pt_BR');
        $firstAuthor->setFamilyName('Castanheiras', 'pt_BR');
        $firstAuthor->setAffiliation('Lepidus Tecnologia', 'pt_BR');

        $firstAuthor->setData('publicationId', $publication->getId());
        $firstAuthor->setData('rorId', 'https://ror.org/xxxxxxxxrecipient');

        $secondAuthor = new Author();
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

    private function createTestSubmission($journal): Submission
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

        $publication->setData('authors', $authors);
        $publication->setData('submissionId', $submission->getId());
        $submission->setData('currentPublicationId', $publication->getId());
        $submission->setData('publications', [$publication]);
        $submission->setLicenseUrl('https://creativecommons.org/licenses/by-nc-nd/4.0/');

        return $submission;
    }

    public function testGetRecipientByFirstAuthorWithRORAssociated()
    {
        $recipientRor = $this->P1Pio->getRecipientAddress();
        $this->assertEquals('https://ror.org/xxxxxxxxrecipient', $recipientRor);
    }

    public function testGetRecipientBySecondAuthorWithRORAssociated()
    {
        $authors = $this->submission->getAuthors();
        $firstAuthor = $authors[0];
        $firstAuthor->setData('rorId', null);
        $secondAuthor = $authors[1];
        $secondAuthor->setData('rorId', 'https://ror.org/secondRecipient');
        $publication = $this->submission->getCurrentPublication();
        $publication->setData("authors", [$firstAuthor, $secondAuthor]);
        $P1Pio = new P1Pio($this->submission);

        $recipientRor = $P1Pio->getRecipientAddress();
        $this->assertEquals('https://ror.org/secondRecipient', $recipientRor);
    }

    public function testRecipientAddressIsFirstAuthorFirstInstitutionAddress()
    {
        $recipientAddress = $this->P1Pio->getRecipientAddress();
        $institutionRor = $this->P1Pio->getAuthorsData()[0]['institutions'][0]['ror'];

        $this->assertEquals($institutionRor, $recipientAddress);
    }

    public function testGetAuthorGivenName()
    {
        $authorsData = $this->P1Pio->getAuthorsData();
        $authorGivenName = $authorsData[0]['firstName'];
        $this->assertEquals($authorGivenName, 'Iris');
    }

    public function testGetAuthorLastName()
    {
        $authorsData = $this->P1Pio->getAuthorsData();
        $authorFamilyName = $authorsData[0]['lastName'];
        $this->assertEquals($authorFamilyName, 'Castanheiras');
    }

    public function testAuthorInstitutionAddress()
    {
        $authorsData = $this->P1Pio->getAuthorsData();
        $institution = $authorsData[0]['institutions'][0];
        $this->assertTrue(is_array($institution));
        $this->assertEquals($institution['ror'], 'https://ror.org/xxxxxxxxrecipient');
    }

    public function testGetAuthorInstitutionName()
    {
        $authorsData = $this->P1Pio->getAuthorsData();
        $institution = $authorsData[0]['institutions'][0];
        $this->assertTrue(is_array($institution));
        $this->assertEquals($institution['name'], 'Lepidus Tecnologia');
    }

    public function testGetAuthorLocalizedAffiliation()
    {
        $authorsData = $this->P1Pio->getAuthorsData();
        $affiliation = $authorsData[0]['affiliation'];
        $this->assertEquals($affiliation, 'Lepidus Tecnologia');
    }

    public function testGetArticleTitle()
    {
        $articleData = $this->P1Pio->getArticleData();
        $title = $articleData['title'];
        $this->assertEquals($title, 'The International relations of Middle-Earth');
    }

    public function testGetArticleDoi()
    {
        $articleData = $this->P1Pio->getArticleData();
        $doi = $articleData['doi'];
        $this->assertEquals($doi, 'https://doi.org/00.0000/mearth.0000');
    }

    public function testGetArticleType()
    {
        $articleData = $this->P1Pio->getArticleData();
        $type = $articleData['type'];
        $this->assertEquals($type, 'research-article');
    }

    public function testGetArticleVor()
    {
        $articleData = $this->P1Pio->getArticleData();
        $vor = $articleData['vor'];
        $this->assertEquals('pure OA journal', $vor['publication']);
        $this->assertEquals('CC BY-NC-ND', $vor['license']);
    }

    public function testGetJournalName()
    {
        $journalData = $this->P1Pio->getJournalData();
        $this->assertEquals('Middle Earth papers', $journalData['name']);
        $this->assertEquals('0000-0001', $journalData['id']);
    }

    public function testP1PioMessageHeader()
    {
        $header = $this->P1Pio->getContent()['header'];
        $this->assertEquals('p1', $header['type']);
        $this->assertEquals('v2', $header['version']);
        $this->assertEquals($this->getExpectedRecipient(), $header['to']);
        $this->assertEquals(true, $header['persistent']);
        $this->assertEquals(true, $header['pio']);
    }

    public function testP1PioMessageData()
    {
        $data = $this->P1Pio->getContent()['data'];
        $this->assertEquals('VoR', $data['timing']);
        $this->assertEquals($this->getExpectedAuthorsArray(), $data['authors']);
        $this->assertEquals($this->getExpectedArticleObject(), $data['article']);
        $this->assertEquals($this->getExpectedJournalArray(), $data['journal']);
    }

    public function testValidateHasMinimumSubmissionDataReturnsEmptyIfAllMandatoryDataIsPassed()
    {
        $this->assertTrue(empty($this->P1Pio->validateHasMinimumSubmissionData()));
    }

    public function testValidateHasMinimumSubmissionDataShouldReturnMessageIfFirstAuthorDoesNotHaveROR()
    {
        $firstAuthor = $this->submission->getAuthors()[0];
        $firstAuthor->setData('rorId', null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "##plugins.generic.OASwitchboard.postRequirementsError##"
        );
        $P1Pio = new P1Pio($this->submission);
    }

    public function testValidateHasMinimumSubmissionDataShouldReturnMessageIfAuthorDoesNotHaveFamilyName()
    {
        $firstAuthor = $this->submission->getAuthors()[0];
        $firstAuthor->setData('familyName', null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "##plugins.generic.OASwitchboard.postRequirementsError##"
        );
        $P1Pio = new P1Pio($this->submission);
    }

    public function testValidateHasMinimumSubmissionDataShouldReturnMessagesIfAuthorDoesNotHaveAffiliation()
    {
        $firstAuthor = $this->submission->getAuthors()[0];
        $firstAuthor->setData('affiliation', null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "##plugins.generic.OASwitchboard.postRequirementsError##"
        );
        $P1Pio = new P1Pio($this->submission);
    }

    public function testValidateHasMinimumSubmissionDataShouldReturnMessagesIfArticleDoesNotHaveDOIAssociated()
    {
        $publication = $this->submission->getCurrentPublication();
        $publication->setData('pub-id::doi', null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "##plugins.generic.OASwitchboard.postRequirementsError##"
        );
        $P1Pio = new P1Pio($this->submission);
    }

    public function testValidateHasMinimumSubmissionDataShouldReturnMessagesIfArticleDoesNotHaveISSNAssociated()
    {
        $journal = $this->createMockedJournal();
        $submission = $this->createTestSubmission($journal);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "##plugins.generic.OASwitchboard.postRequirementsError##"
        );
        $P1Pio = new P1Pio($submission);
    }
}
