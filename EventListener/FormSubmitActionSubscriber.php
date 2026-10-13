<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\EventListener;

use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\FormEvents;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity\FormActionExecutionLog;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity\FormActionExecutionLogRepository;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Service\ConditionsEvaluator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Replaces SubmissionModel decoration: core SubmissionModel is final in Mautic 7,
 * so conditional execution is gated on FormEvents::ON_EXECUTE_SUBMIT_ACTION instead.
 */
final class FormSubmitActionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Config $pluginConfig,
        private readonly ConditionsEvaluator $conditionsEvaluator,
        private readonly FormActionExecutionLogRepository $logRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::ON_EXECUTE_SUBMIT_ACTION => [
                ['gateSubmitAction', 255],
                ['logExecutedSubmitAction', -255],
            ],
        ];
    }

    /**
     * Skip the action (and later listeners) when conditions are not met.
     */
    public function gateSubmitAction(SubmissionEvent $event): void
    {
        if (!$this->pluginConfig->isPublished()) {
            return;
        }

        $action = $event->getAction();
        if (null === $action) {
            return;
        }

        $lead = $event->getSubmission()->getLead();
        if (null === $lead) {
            return;
        }

        $shouldExecute = $this->conditionsEvaluator->shouldExecute(
            $action,
            $event->getSubmission(),
            $lead
        );

        if ($shouldExecute) {
            return;
        }

        $this->logRepository->logExecution(
            $event->getSubmission(),
            $action,
            false,
            FormActionExecutionLog::DETAILS_CONDITIONS_NOT_MET
        );

        $event->stopPropagation();
    }

    /**
     * Record a successful (conditions met) execution after action listeners ran.
     */
    public function logExecutedSubmitAction(SubmissionEvent $event): void
    {
        if (!$this->pluginConfig->isPublished()) {
            return;
        }

        if ($event->isPropagationStopped()) {
            return;
        }

        $action = $event->getAction();
        if (null === $action) {
            return;
        }

        $this->logRepository->logExecution(
            $event->getSubmission(),
            $action,
            true,
            FormActionExecutionLog::DETAILS_CONDITIONS_MET
        );
    }
}
