<?php

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\FormBundle\Event\FormEvent;
use Mautic\FormBundle\FormEvents;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity\FormActionCondition;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Model\ActionConditionManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class FormBuilderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ActionConditionManager $actionConditionManager,
        private RequestStack $requestStack,
        private Config $pluginConfig,
        private EntityManagerInterface $em
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_PRE_SAVE  => ['onFormPreSave', 0],
            FormEvents::FORM_POST_SAVE => ['onFormPostSave', 0],
        ];
    }

    public function onFormPreSave(FormEvent $event): void
    {
        if (!$this->pluginConfig->isPublished()) {
            return;
        }

        // Detach any loaded FormActionCondition entities to avoid UoW processing them
        // after actions were deleted in the controller's deleteActions() flush.
        // @phpstan-ignore-next-line
        $this->em->clear(FormActionCondition::class);
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
