<?php

namespace APP\plugins\generic\OASwitchboard\classes\api;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\OASwitchboard\classes\OASwitchboardService;
use APP\plugins\generic\OASwitchboard\classes\exceptions\P1PioException;
use APP\plugins\generic\OASwitchboard\classes\messages\P1Pio;
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

    public function addRoute(string $hookName, &$apiController, APIHandler $apiHandler): bool
    {
        $apiHandler->addRoute(
            'GET',
            '{submissionId}/oaSwitchboardStatus',
            fn (IlluminateRequest $request, int $submissionId) => $this->getStatus($submissionId),
            'oaSwitchboard.status',
            [
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_AUTHOR,
            ],
        );

        return false;
    }

    private function getStatus(int $submissionId): JsonResponse
    {
        $submission = Repo::submission()->get($submissionId);
        if (!$submission) {
            return response()->json(['error' => 'submission not found'], Response::HTTP_NOT_FOUND);
        }

        $contextId = Application::get()->getRequest()->getContext()->getId();
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
