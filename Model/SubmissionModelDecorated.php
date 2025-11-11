<?php

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Model;

use Mautic\FormBundle\Entity\Action;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\Model\SubmissionModel;

class SubmissionModelDecorated extends SubmissionModel
{
    protected function executeFormActions(SubmissionEvent $event): void
    {
        $actions          = $event->getSubmission()->getForm()->getActions();
        $customComponents = $this->formModel->getCustomComponents();
        $availableActions = $customComponents['actions'] ?? [];

        $actions->filter(fn (Action $action): bool => array_key_exists($action->getType(), $availableActions))->map(function (Action $action) use ($event, $availableActions): void {
            $event->setAction($action);
            $this->dispatcher->dispatch($event, $availableActions[$action->getType()]['eventName']);
        });
    }

}