<?php

namespace APP\plugins\generic\OASwitchboard\classes\api;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\OASwitchboard\classes\Message;
use APP\plugins\generic\OASwitchboard\classes\OASwitchboardService;
use APP\plugins\generic\OASwitchboard\classes\SendStatus;
use APP\submission\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Http\Response;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
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
            $this->getOASwitchboardStatus(...),
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
            $this->resendOASwitchboardMessage(...),
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

    protected function canOfferSend(array $missingFields, Submission $submission, int $contextId): bool
    {
        return empty($missingFields)
            && $this->isPublished($submission)
            && $this->currentUserCanResend($contextId)
            && SendStatus::canRetry($submission);
    }

    protected function wasPublishedBeforePluginInstallation(Submission $submission): bool
    {
        if (!$this->isPublished($submission)) {
            return false;
        }

        $installationDate = $this->getPluginInstallationDate();
        $datePublished = $submission->getCurrentPublication()?->getData('datePublished');
        if (empty($installationDate) || empty($datePublished)) {
            return false;
        }

        return substr($datePublished, 0, 10) < substr($installationDate, 0, 10);
    }

    protected function getPluginInstallationDate(): ?string
    {
        $versions = DAORegistry::getDAO('VersionDAO')->getVersionHistory('plugins.generic', 'OASwitchboard');
        if (empty($versions)) {
            return null;
        }

        return min(array_map(fn ($version) => $version->getDateInstalled(), $versions));
    }

    protected function currentUserCanResend(int $contextId): bool
    {
        $user = Application::get()->getRequest()->getUser();
        return $user
            && (
                $user->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR], $contextId)
                || $user->hasRole([Role::ROLE_ID_SITE_ADMIN], Application::SITE_CONTEXT_ID)
            );
    }

    private function submissionNotFound(): JsonResponse
    {
        return response()->json(
            ['error' => __('api.404.resourceNotFound')],
            Response::HTTP_NOT_FOUND
        );
    }

    private function resendOASwitchboardMessage(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $submissionId = (int) $illuminateRequest->route('submissionId');
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

        if (!empty(OASwitchboardService::getMissingRequirements($submission, Application::get()->getRequest()->getContext()))) {
            return response()->json(
                ['error' => __('plugins.generic.OASwitchboard.postRequirementsError')],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $wasScheduled = (new Message($this->plugin))->retrySendToOASwitchboard($submission);
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
            ['sendStatus' => SendStatus::read($submission)],
            Response::HTTP_OK
        );
    }

    private function getOASwitchboardStatus(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $submissionId = (int) $illuminateRequest->route('submissionId');
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

        $sendStatus = SendStatus::read($submission);
        $missingFields = $pluginConfigured
            ? OASwitchboardService::getMissingRequirements($submission, Application::get()->getRequest()->getContext())
            : [];

        $payload = [
            'pluginConfigured' => $pluginConfigured,
            'readyToSend' => $pluginConfigured && empty($missingFields),
            'missingFields' => $missingFields,
            'hasRor' => OASwitchboardService::isRorAssociated($submission),
            'sendStatus' => $sendStatus,
            'isPublished' => $this->isPublished($submission),
            'publishedBeforePlugin' => $sendStatus === null && $this->wasPublishedBeforePluginInstallation($submission),
            'canRetry' => $pluginConfigured && $this->canOfferSend($missingFields, $submission, $contextId),
        ];

        return response()->json($payload, Response::HTTP_OK);
    }
}
