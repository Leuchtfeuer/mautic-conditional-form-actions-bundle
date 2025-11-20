<?php

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\EventListener;

use Mautic\FormBundle\Event\FormEvent;
use Mautic\FormBundle\FormEvents;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Model\ActionConditionManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class FormBuilderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ActionConditionManager $actionConditionManager,
        private RequestStack $requestStack,
        private Config $pluginConfig
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_POST_SAVE => ['onFormPostSave', 0],
        ];
    }

    public function onFormPostSave(FormEvent $event): void
    {
        if (!$this->pluginConfig->isPublished()) {
            return;
        }

        $form       = $event->getForm();
        $request    = $this->requestStack->getCurrentRequest();
        $mauticForm = $request->request->all()['mauticform'] ?? null;

        if (!isset($mauticForm) || !is_array($mauticForm)) {
            return;
        }

        $actionConditionsConfig = $mauticForm['actionConditionsConfig'] ?? [];
        $actionConditionsData   = $actionConditionsConfig['actionConditions'] ?? [];

        $this->actionConditionManager->saveActionConditions($form, $actionConditionsData);
    }
}
