<?php

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Model;

use Mautic\FormBundle\Entity\Action;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\Model\SubmissionModel;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Service\ConditionsEvaluator;
use Symfony\Contracts\Service\Attribute\Required;

class SubmissionModelDecorated extends SubmissionModel
{
    private ConditionsEvaluator $conditionsEvaluator;

    #[Required]
    public function setConditionsEvaluator(ConditionsEvaluator $conditionsEvaluator): void
    {
        $this->conditionsEvaluator = $conditionsEvaluator;
    }


    protected function executeFormActions(SubmissionEvent $event): void
    {
        $actions          = $event->getSubmission()->getForm()->getActions();
        $customComponents = $this->formModel->getCustomComponents();
        $availableActions = $customComponents['actions'] ?? [];

        $validActions = $actions->filter(function (Action $action) use ($availableActions, $event) {
            $isActionTypeAvailable = array_key_exists($action->getType(), $availableActions);
            $shouldExecuteAction = $this->conditionsEvaluator->shouldExecute(
                $action,
                $event->getSubmission(),
                $event->getLead()
            );

            return $isActionTypeAvailable && $shouldExecuteAction;
        });

        $validActions->map(
            function (Action $action) use ($event, $availableActions): void {
                $event->setAction($action);
                $this->dispatcher->dispatch($event, $availableActions[$action->getType()]['eventName']);
            }
        );
    }

}