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
use PKP\core\PKPRequest;
use PKP\handler\APIHandler;
use PKP\plugins\Hook;
use PKP\plugins\interfaces\HasAuthorizationPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;

class OASwitchboardStatusController implements HasAuthorizationPolicy
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
            $this,
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
            $this,
        );

        return false;
    }

    public function getPolicies(PKPRequest $request, array &$args, array $roleAssignments): array
    {
        return [new SubmissionAccessPolicy($request, $args, $roleAssignments)];
    }


    // Prevents cross-context access to a submission.
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

    private function submissionNotFound(): JsonResponse
    {
        return response()->json(
            ['error' => __('api.404.resourceNotFound')],
            Response::HTTP_NOT_FOUND
        );
    }

    private function resend(int $submissionId): JsonResponse
    {
        $contextId = $this->getRequestContextId();
        $submission = $this->getSubmissionInContext($submissionId, $contextId);
        if (!$submission) {
            return $this->submissionNotFound();
        }

        if (!$this->isPublished($submission)) {
            return response()->json(
                ['error' => __('plugins.generic.OASwitchboard.resend.notPublished')],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!SendStatus::canRetry($submission)) {
            return response()->json(
                ['error' => __('plugins.generic.OASwitchboard.resend.notFailed')],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $wasScheduled = (new Message($this->plugin))->retryFailedSendToOASwitchboard($submission);
            if (!$wasScheduled) {
                return response()->json(
                    ['error' => __('plugins.generic.OASwitchboard.resend.notFailed')],
                    Response::HTTP_BAD_REQUEST
                );
            }
        } catch (\Exception $exception) {
            return response()->json(
                ['error' => __('plugins.generic.OASwitchboard.serverError')],
                Response::HTTP_BAD_REQUEST
            );
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
            return $this->submissionNotFound();
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
