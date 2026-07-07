<?php

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Model;

use Mautic\FormBundle\Entity\Action;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\Model\SubmissionModel;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity\FormActionExecutionLog;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity\FormActionExecutionLogRepository;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Service\ConditionsEvaluator;
use Symfony\Contracts\Service\Attribute\Required;

class SubmissionModelDecorated extends SubmissionModel
{
    private ConditionsEvaluator $conditionsEvaluator;

    private FormActionExecutionLogRepository $logRepository;

    #[Required]
    public function setConditionsEvaluator(ConditionsEvaluator $conditionsEvaluator): void
    {
        $this->conditionsEvaluator = $conditionsEvaluator;
    }

    #[Required]
    public function setLogRepository(FormActionExecutionLogRepository $logRepository): void
    {
        $this->logRepository = $logRepository;
    }

    protected function executeFormActions(SubmissionEvent $event): void
    {
        $actions          = $event->getSubmission()->getForm()->getActions();
        $customComponents = $this->formModel->getCustomComponents();
        $availableActions = $customComponents['actions'] ?? [];

        /** @var Action $action */
        foreach ($actions as $action) {
            $type = $action->getType();

            if (!array_key_exists($type, $availableActions)) {
                continue;
            }

            $shouldExecute = $this->conditionsEvaluator->shouldExecute(
                $action,
                $event->getSubmission(),
                $event->getLead()
            );

            if (!$shouldExecute) {
                $this->logRepository->logExecution(
                    $event->getSubmission(),
                    $action,
                    false,
                    FormActionExecutionLog::DETAILS_CONDITIONS_NOT_MET
                );
                continue;
            }

            try {
                $event->setAction($action);
                $this->dispatcher->dispatch($event, $availableActions[$type]['eventName']);

                $this->logRepository->logExecution(
                    $event->getSubmission(),
                    $action,
                    true,
                    FormActionExecutionLog::DETAILS_CONDITIONS_MET
                );
            } catch (\Throwable $e) {
                $this->logRepository->logExecution(
                    $event->getSubmission(),
                    $action,
                    true,
                    FormActionExecutionLog::DETAILS_ERROR
                );
                throw $e;
            }
        }
    }
}
