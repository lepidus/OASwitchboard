<?php

namespace APP\plugins\generic\OASwitchboard\classes\api;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\OASwitchboard\classes\exceptions\P1PioException;
use APP\plugins\generic\OASwitchboard\classes\Message;
use APP\plugins\generic\OASwitchboard\classes\messages\P1Pio;
use APP\plugins\generic\OASwitchboard\classes\OASwitchboardService;
use APP\plugins\generic\OASwitchboard\classes\SendStatus;
use APP\submission\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Http\Response;
use PKP\handler\APIHandler;
use PKP\plugins\Hook;
use PKP\security\Role;

class OASwitchboardStatusController
{
    private $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    public function register(): void
    {
        Hook::add('APIHandler::endpoints::_submissions', [$this, 'addRoute']);
    }

    public function addRoute(string $hookName, $apiController, APIHandler $apiHandler): bool
    {
        $apiHandler->addRoute(
            'GET',
            '{submissionId}/oaSwitchboardStatus',
            fn (IlluminateRequest $request) => $this->getStatus((int) $request->route('submissionId')),
            'oaSwitchboard.status',
            [
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_AUTHOR,
            ],
        );

        $apiHandler->addRoute(
            'POST',
            '{submissionId}/oaSwitchboardResend',
            fn (IlluminateRequest $request) => $this->resend((int) $request->route('submissionId')),
            'oaSwitchboard.resend',
            [
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
            ],
        );

        return false;
    }

    /**
     * Loads the submission scoped to the request context. Returning null for a
     * submission that belongs to another context prevents cross-context access
     * (the role middleware only checks the user's roles in the current context,
     * not that the submission itself belongs to it).
     */
    protected function getSubmissionInContext(int $submissionId, int $contextId): ?Submission
    {
        return Repo::submission()->get($submissionId, $contextId);
    }

    protected function getRequestContextId(): int
    {
        return Application::get()->getRequest()->getContext()->getId();
    }

    protected function isPublished(Submission $submission): bool
    {
        return $submission->getData('status') === Submission::STATUS_PUBLISHED;
    }

    private function resend(int $submissionId): JsonResponse
    {
        $contextId = $this->getRequestContextId();
        $submission = $this->getSubmissionInContext($submissionId, $contextId);
        if (!$submission) {
            return response()->json(['error' => 'submission not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isPublished($submission)) {
            return response()->json(
                ['error' => __('plugins.generic.OASwitchboard.resend.notPublished')],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $message = new Message($this->plugin);
            $message->scheduleSendToOASwitchboard($submission);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return response()->json(
            ['sendStatus' => SendStatus::readFromSubmission($submission)],
            Response::HTTP_OK
        );
    }

    private function getStatus(int $submissionId): JsonResponse
    {
        $contextId = $this->getRequestContextId();
        $submission = $this->getSubmissionInContext($submissionId, $contextId);
        if (!$submission) {
            return response()->json(['error' => 'submission not found'], Response::HTTP_NOT_FOUND);
        }

        $pluginConfigured = true;
        try {
            OASwitchboardService::validatePluginIsConfigured($this->plugin, $contextId);
        } catch (\Exception $e) {
            $pluginConfigured = false;
        }

        $payload = [
            'pluginConfigured' => $pluginConfigured,
            'readyToSend' => false,
            'missingFields' => [],
            'hasRor' => OASwitchboardService::isRorAssociated($submission),
            'sendStatus' => SendStatus::readFromSubmission($submission),
        ];

        if (!$pluginConfigured) {
            return response()->json($payload, Response::HTTP_OK);
        }

        try {
            new P1Pio($submission);
            $payload['readyToSend'] = true;
        } catch (P1PioException $e) {
            $payload['missingFields'] = array_values(array_unique($e->getP1PioErrors() ?? []));
        }

        return response()->json($payload, Response::HTTP_OK);
    }
}
