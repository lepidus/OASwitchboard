<?php

import('lib.pkp.tests.PKPTestCase');
import('classes.submission.Submission');
import('plugins.generic.OASwitchboard.classes.Message');
import('plugins.generic.OASwitchboard.tests.helpers.CreatesPluginMocks');

class MessageIdentifierLoadingTest extends PKPTestCase
{
    use CreatesPluginMocks;

    private function createMessage(bool $schemaHasIdentifiers, ArrayObject $loadedContextIds)
    {
        return new class ($this->createPluginMock([]), $schemaHasIdentifiers, $loadedContextIds) extends Message {
            private $schemaHasIdentifiers;
            private $loadedContextIds;

            public function __construct($plugin, $schemaHasIdentifiers, $loadedContextIds)
            {
                parent::__construct($plugin);
                $this->schemaHasIdentifiers = $schemaHasIdentifiers;
                $this->loadedContextIds = $loadedContextIds;
            }

            public function exposedGetSubmissionWithPublicationIdentifiers($submission, $contextId)
            {
                return $this->getSubmissionWithPublicationIdentifiers($submission, $contextId);
            }

            protected function publicationSchemaHasIdentifiers(): bool
            {
                return $this->schemaHasIdentifiers;
            }

            protected function loadPublicationIdentifierPlugins($contextId): void
            {
                $this->loadedContextIds->append($contextId);
            }

            protected function reloadSubmission($submission)
            {
                $reloaded = new Submission();
                $reloaded->setId($submission->getId());
                return $reloaded;
            }
        };
    }

    private function buildSubmission(): Submission
    {
        $submission = new Submission();
        $submission->setId(17);
        return $submission;
    }

    public function testShouldNotLoadIdentifierPluginsWhenTheSchemaAlreadyHasTheDoi()
    {
        $loadedContextIds = new ArrayObject();
        $submission = $this->buildSubmission();

        $result = $this->createMessage(true, $loadedContextIds)->exposedGetSubmissionWithPublicationIdentifiers($submission, 1);

        $this->assertSame($submission, $result);
        $this->assertCount(0, $loadedContextIds);
    }

    public function testShouldLoadIdentifierPluginsOncePerJournal()
    {
        $loadedContextIds = new ArrayObject();
        $message = $this->createMessage(false, $loadedContextIds);

        $first = $message->exposedGetSubmissionWithPublicationIdentifiers($this->buildSubmission(), 1);
        $message->exposedGetSubmissionWithPublicationIdentifiers($this->buildSubmission(), 1);
        $message->exposedGetSubmissionWithPublicationIdentifiers($this->buildSubmission(), 2);

        $this->assertSame([1, 2], $loadedContextIds->getArrayCopy());
        $this->assertSame(17, $first->getId());
    }
}
