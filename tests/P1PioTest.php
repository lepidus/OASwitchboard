<?php

namespace APP\plugins\generic\OASwitchboard\tests;

use APP\plugins\generic\OASwitchboard\classes\messages\P1Pio;
use APP\plugins\generic\OASwitchboard\tests\helpers\ObjectFactory;
use APP\plugins\generic\OASwitchboard\tests\helpers\P1PioExpectedTestData;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\decision\Decision;
use PKP\tests\PKPTestCase;

class P1PioTest extends PKPTestCase
{
    use P1PioExpectedTestData;

    private $P1Pio;
    private $submission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockRequest();
        $journal = ObjectFactory::createMockedJournal($onlineIssn = '0000-0001', $printIssn = '0000-0002');
        $this->submission = ObjectFactory::createTestSubmission($journal);
        ObjectFactory::registerStubFundingPlugin([[
            'id' => 1,
            'name' => 'Universidade Federal de Santa Catarina',
            'identification' => 'http://dx.doi.org/10.13039/501100007082',
            'awardNumbers' => [10 => '2021/12345-6'],
        ]]);
        $this->P1Pio = ObjectFactory::createP1PioMock($this->submission);
    }

    protected function tearDown(): void
    {
        ObjectFactory::$creatableFundingDaos = [];
        parent::tearDown();
    }

    protected function getMockedDAOs(): array
    {
        return [...parent::getMockedDAOs(), 'JournalDAO', 'GenreDAO'];
    }

    protected function getMockedRegistryKeys(): array
    {
        return [...parent::getMockedRegistryKeys(), 'site', 'plugins', 'daos'];
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

    public function testP1PioAuthorsShouldExcludeEmailAndPreserveAllowedIdentityFields()
    {
        $firstAuthor = $this->P1Pio->getAuthorsData()[0];

        $this->assertArrayNotHasKey('email', $firstAuthor);
        $this->assertEquals('Iris', $firstAuthor['firstName']);
        $this->assertEquals('Castanheiras', $firstAuthor['lastName']);
        $this->assertEquals('Lepidus Tecnologia', $firstAuthor['affiliation']);
        $this->assertEquals('https://orcid.org/0000-0000-0000-0000', $firstAuthor['orcid']);
        $this->assertEquals('Lepidus Tecnologia', $firstAuthor['institutions'][0]['name']);
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

    public function testArticlePublicationDateShouldComeFromTheCurrentPublication()
    {
        $this->submission->getCurrentPublication()->setData('datePublished', '2022-07-15');

        $dates = $this->P1Pio->getArticleData()['manuscript']['dates'];

        $this->assertSame('2022-07-15', $dates['publication']);
    }

    public function testArticleDatesShouldBeEmptyWhenUnknown()
    {
        $this->submission->getCurrentPublication()->setData('datePublished', null);
        $this->submission->setData('dateSubmitted', null);

        $dates = $this->P1Pio->getArticleData()['manuscript']['dates'];

        $this->assertSame('', $dates['submission']);
        $this->assertSame('', $dates['publication']);
    }

    public function testArticleShouldOmitManuscriptIdWhenTheGalleyFileNoLongerExists()
    {
        $message = new class ($this->submission) extends P1Pio {
            public function getSubmissionDecisions(): array
            {
                return [];
            }
        };

        $this->assertArrayNotHasKey('id', $message->getArticleData()['manuscript']);
    }

    public function testArticleShouldOmitManuscriptIdWhenTheJournalHasNoArticleTextGenre()
    {
        DAORegistry::registerDAO('GenreDAO', new class () {
            public function getByKey($key, $contextId = null)
            {
                return null;
            }
        });

        $articleData = ObjectFactory::createP1PioMock($this->submission)->getArticleData();

        $this->assertArrayNotHasKey('id', $articleData['manuscript']);
    }

    public function testAcceptanceDateShouldComeFromAnAcceptAndSkipReviewDecision()
    {
        $message = ObjectFactory::createP1PioMock($this->submission, [
            ObjectFactory::buildDecision(Decision::SKIP_EXTERNAL_REVIEW, '2021-02-10 10:00:00', 1),
        ]);

        $this->assertSame('2021-02-10', $message->getArticleData()['manuscript']['dates']['acceptance']);
    }

    public function testAcceptanceDateShouldBeTheLatestAcceptance()
    {
        $message = ObjectFactory::createP1PioMock($this->submission, [
            ObjectFactory::buildDecision(Decision::ACCEPT, '2021-02-01 10:00:00'),
            ObjectFactory::buildDecision(Decision::BACK_FROM_COPYEDITING, '2021-02-05 10:00:00', 4),
            ObjectFactory::buildDecision(Decision::ACCEPT, '2021-02-20 10:00:00'),
            ObjectFactory::buildDecision(Decision::SEND_TO_PRODUCTION, '2021-02-25 10:00:00', 4),
        ]);

        $this->assertSame('2021-02-20', $message->getArticleData()['manuscript']['dates']['acceptance']);
    }

    public function testAcceptanceDateShouldBeEmptyWithoutAnAcceptance()
    {
        $message = ObjectFactory::createP1PioMock($this->submission, [
            ObjectFactory::buildDecision(Decision::EXTERNAL_REVIEW, '2021-01-15 10:00:00', 1),
        ]);

        $this->assertSame('', $message->getArticleData()['manuscript']['dates']['acceptance']);
    }

    public function testArticleGrantsShouldSkipBlankAndRepeatedAwardNumbers()
    {
        ObjectFactory::registerStubFundingPlugin([
            [
                'id' => 1,
                'name' => 'Fundação de Amparo à Pesquisa do Estado de São Paulo',
                'identification' => 'https://doi.org/10.13039/501100001807',
                'awardNumbers' => [10 => '2021/12345-6', 11 => '  ', 12 => ''],
            ],
            [
                'id' => 2,
                'name' => 'Conselho Nacional de Desenvolvimento Científico e Tecnológico',
                'identification' => 'https://doi.org/10.13039/501100003593',
                'awardNumbers' => [13 => ' 2021/12345-6 '],
            ],
        ]);

        $this->assertEquals([['id' => '2021/12345-6']], $this->P1Pio->getArticleData()['grants']);
    }

    public function testArticleFundersShouldSkipFundersWithoutNameOrIdentification()
    {
        ObjectFactory::registerStubFundingPlugin([
            ['id' => 1, 'name' => '', 'identification' => '', 'awardNumbers' => [10 => 'ORPHAN-1']],
            ['id' => 2, 'name' => 'CNPq', 'identification' => '', 'awardNumbers' => []],
        ]);

        $articleData = $this->P1Pio->getArticleData();

        $this->assertEquals([['name' => 'CNPq', 'fundref' => '']], $articleData['funders']);
        $this->assertArrayNotHasKey('grants', $articleData);
    }

    public function testArticleShouldReadTheFundersOnlyOnce()
    {
        $this->P1Pio->getArticleData();
        $funderDao = DAORegistry::getDAO('FunderDAO');
        $funderDao->calls = 0;

        $this->P1Pio->getArticleData();

        $this->assertSame(1, $funderDao->calls);
    }

    public function testFundingDaosShouldNotBeHalfRegisteredWhenOneCannotBeCreated()
    {
        $daos = & DAORegistry::getDAOs();
        unset($daos['FunderDAO'], $daos['FunderAwardDAO']);
        ObjectFactory::$creatableFundingDaos = ['FunderDAO' => ObjectFactory::createStubFundingDaos([])['FunderDAO']];

        $articleData = $this->P1Pio->getArticleData();

        $this->assertArrayNotHasKey('funders', $articleData);
        $this->assertArrayNotHasKey('FunderDAO', DAORegistry::getDAOs());
    }

    public function testArticleGrantsShouldListAwardNumbersOfAllFunders()
    {
        ObjectFactory::registerStubFundingPlugin([
            [
                'id' => 1,
                'name' => 'Fundação de Amparo à Pesquisa do Estado de São Paulo',
                'identification' => 'https://doi.org/10.13039/501100001807',
                'awardNumbers' => [10 => '2021/12345-6', 11 => '2022/65432-1'],
            ],
            [
                'id' => 2,
                'name' => 'Conselho Nacional de Desenvolvimento Científico e Tecnológico',
                'identification' => 'https://doi.org/10.13039/501100003593',
                'awardNumbers' => [12 => 'CNPq-999'],
            ],
        ]);

        $articleData = $this->P1Pio->getArticleData();

        $this->assertEquals(
            [['id' => '2021/12345-6'], ['id' => '2022/65432-1'], ['id' => 'CNPq-999']],
            $articleData['grants']
        );
    }

    public function testArticleShouldOmitGrantsWhenFundersHaveNoAwardNumbers()
    {
        ObjectFactory::registerStubFundingPlugin([[
            'id' => 1,
            'name' => 'Universidade Federal de Santa Catarina',
            'identification' => 'http://dx.doi.org/10.13039/501100007082',
            'awardNumbers' => [],
        ]]);

        $articleData = $this->P1Pio->getArticleData();

        $this->assertArrayHasKey('funders', $articleData);
        $this->assertArrayNotHasKey('grants', $articleData);
    }

    public function testArticleShouldOmitFundersAndGrantsWhenFundingPluginIsDisabled()
    {
        ObjectFactory::registerStubFundingPlugin([[
            'id' => 1,
            'name' => 'Universidade Federal de Santa Catarina',
            'identification' => 'http://dx.doi.org/10.13039/501100007082',
            'awardNumbers' => [10 => '2021/12345-6'],
        ]], $enabledContextId = null);

        $articleData = $this->P1Pio->getArticleData();

        $this->assertArrayNotHasKey('funders', $articleData);
        $this->assertArrayNotHasKey('grants', $articleData);
    }

    public function testArticleShouldOmitFundersAndGrantsWhenFundingPluginIsNotInstalled()
    {
        Registry::delete('plugins');

        $articleData = $this->P1Pio->getArticleData();

        $this->assertArrayNotHasKey('funders', $articleData);
        $this->assertArrayNotHasKey('grants', $articleData);
    }

    public function testArticleShouldOmitFundersAndGrantsWhenFundingPluginIsEnabledOnlyForAnotherJournal()
    {
        ObjectFactory::registerStubFundingPlugin([[
            'id' => 1,
            'name' => 'Universidade Federal de Santa Catarina',
            'identification' => 'http://dx.doi.org/10.13039/501100007082',
            'awardNumbers' => [10 => '2021/12345-6'],
        ]], $enabledContextId = 2);

        $articleData = $this->P1Pio->getArticleData();

        $this->assertArrayNotHasKey('funders', $articleData);
        $this->assertArrayNotHasKey('grants', $articleData);
    }

    public function testArticleShouldIncludeFundersAndGrantsWhenFundingPluginWasLoadedWithoutItsDaos()
    {
        $fundingDaos = ObjectFactory::createStubFundingDaos([[
            'id' => 1,
            'name' => 'Universidade Federal de Santa Catarina',
            'identification' => 'http://dx.doi.org/10.13039/501100007082',
            'awardNumbers' => [10 => '2021/12345-6'],
        ]]);
        $daos = & DAORegistry::getDAOs();
        unset($daos['FunderDAO'], $daos['FunderAwardDAO']);
        ObjectFactory::$creatableFundingDaos = $fundingDaos;

        $articleData = $this->P1Pio->getArticleData();

        $this->assertEquals('Universidade Federal de Santa Catarina', $articleData['funders'][0]['name']);
        $this->assertEquals([['id' => '2021/12345-6']], $articleData['grants']);
    }

    public function testArticleShouldOmitFundersAndGrantsWhenFundingPluginDaosCannotBeCreated()
    {
        $daos = & DAORegistry::getDAOs();
        unset($daos['FunderDAO'], $daos['FunderAwardDAO']);

        $articleData = $this->P1Pio->getArticleData();

        $this->assertArrayNotHasKey('funders', $articleData);
        $this->assertArrayNotHasKey('grants', $articleData);
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

    public function testValidateHasMinimumSubmissionDataShouldReturnMessageIfAuthorDoesNotHaveFamilyName()
    {
        $firstAuthor = $this->submission->getCurrentPublication()->getData('authors')[0];
        $firstAuthor->setData('familyName', null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            '##plugins.generic.OASwitchboard.postRequirementsError##'
        );
        $P1Pio = ObjectFactory::createP1PioMock($this->submission);
    }

    public function testValidateHasMinimumSubmissionDataShouldReturnMessagesIfAuthorDoesNotHaveAffiliation()
    {
        $firstAuthor = $this->submission->getCurrentPublication()->getData('authors')[0];
        $firstAuthor->setAffiliations([]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            '##plugins.generic.OASwitchboard.postRequirementsError##'
        );
        $P1Pio = ObjectFactory::createP1PioMock($this->submission);
    }

    public function testValidateHasMinimumSubmissionDataShouldReturnMessagesIfArticleDoesNotHaveDOIAssociated()
    {
        $publication = $this->submission->getCurrentPublication();
        $publication->setData('doiObject', null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            '##plugins.generic.OASwitchboard.postRequirementsError##'
        );
        $P1Pio = ObjectFactory::createP1PioMock($this->submission);
    }

    public function testValidateHasMinimumSubmissionDataShouldReturnMessagesIfArticleDoesNotHaveISSNAssociated()
    {
        $journal = ObjectFactory::createMockedJournal();
        $submission = ObjectFactory::createTestSubmission($journal);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            '##plugins.generic.OASwitchboard.postRequirementsError##'
        );
        $P1Pio = ObjectFactory::createP1PioMock($this->submission);
    }
}
