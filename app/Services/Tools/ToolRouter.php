<?php

namespace App\Services\Tools;

use App\Models\User;
use App\Services\AiCapabilityPolicy;

/**
 * Sends an authorised request to the tool that owns its capability.
 *
 * The router is the single place where a request stops being a question and
 * becomes a database read. It holds the pre-arms (clarification, unsupported,
 * forbidden) that used to sit at the top of InventoryAnswerService::answer(),
 * so they run per sub-request rather than per turn, and it holds the
 * AiCapabilityPolicy gate.
 *
 * That gate is a security boundary, not routing detail. It lives here rather
 * than in the controller so that every caller of the answer path — including
 * the compound executor that fans a turn out into several sub-requests — is
 * authorised by the same code before any inventory row is touched.
 */
class ToolRouter
{
    /** @var array<int, ToolContract> */
    private array $tools;

    public function __construct(
        private AiCapabilityPolicy $policy,
        InventoryTool $inventoryTool,
        AssignmentTool $assignmentTool,
        RequestTool $requestTool,
        MaintenanceTool $maintenanceTool,
        HistoryTool $historyTool,
        DisposalTool $disposalTool,
        ForecastStub $forecastStub,
    ) {
        $this->tools = [
            $inventoryTool,
            $assignmentTool,
            $requestTool,
            $maintenanceTool,
            $historyTool,
            $disposalTool,
            $forecastStub,
        ];
    }

    /**
     * Resolve one request into one ToolResult.
     *
     * @param  array<string, mixed>  $routedQuestion
     * @return array<string, mixed>
     */
    public function dispatch(User $user, array $routedQuestion): array
    {
        $intent = $routedQuestion['intent'] ?? 'unsupported';
        $capability = $routedQuestion['capability'] ?? null;

        if ($intent === 'clarification') {
            return $this->result('clarification', $intent, null, [
                'clarification_question' => $routedQuestion['clarification_question'] ?? 'Which item would you like to check?',
            ]);
        }

        if ($intent === 'unsupported' || $capability === null) {
            return $this->result('unsupported', $intent, $capability);
        }

        if (! $this->policy->allows($user, $capability)) {
            if (($routedQuestion['vague'] ?? false) === true) {
                return $this->result('unsupported', $intent, $capability);
            }

            return $this->result('forbidden', $intent, $capability);
        }

        if (($routedQuestion['needs_clarification'] ?? false) === true) {
            return $this->result('clarification', $intent, $capability, [
                'clarification_question' => $routedQuestion['clarification_question'] ?? 'Which item would you like to check?',
            ]);
        }

        $tool = $this->toolFor($capability);
        if ($tool === null) {
            return $this->result('unsupported', $intent, $capability);
        }

        return $tool->fetch($user, $routedQuestion);
    }

    /**
     * The tool that claims this capability, or null when none does.
     *
     * A capability with no tool stays `unsupported`, which is what procurement
     * priorities have always returned from this path.
     */
    public function toolFor(string $capability): ?ToolContract
    {
        foreach ($this->tools as $tool) {
            if ($tool->handles($capability)) {
                return $tool;
            }
        }

        return null;
    }

    private function result(string $status, ?string $intent, ?string $capability, array $answer = []): array
    {
        return [
            'status' => $status,
            'intent' => $intent,
            'capability' => $capability,
            'answer' => $answer,
            'explanation_data' => $answer,
        ];
    }
}