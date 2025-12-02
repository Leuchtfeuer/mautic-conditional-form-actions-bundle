<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\EventListener;

use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\FormEvents;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Integration\Config;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InjectActionTemplateSubscriber implements EventSubscriberInterface
{
    private const ACTION_TEMPLATE = '@LeuchtfeuerConditionalFormActions/Action/_generic.html.twig';

    public function __construct(
        private Config $pluginConfig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // execute last when all form action settings are populated
            FormEvents::FORM_ON_BUILD => ['onFormBuild', -100],
        ];
    }

    /**
     * We want to set the template for all events;
     * since there is no public method in the event, we have to make it using the ReflectionClass.
     */
    public function onFormBuild(FormBuilderEvent $event): void
    {
        if (!$this->pluginConfig->isPublished()) {
            return;
        }

        // Reflect into the private "actions" property to modify in-place.
        $rc = new \ReflectionClass($event);
        if (!$rc->hasProperty('actions')) {
            return; // property missing or changed in future versions
        }

        $prop    = $rc->getProperty('actions');
        $actions = $prop->getValue($event);
        foreach ($actions as $key => $action) {
            if (empty($action['template'])) {
                $actions[$key]['template'] = self::ACTION_TEMPLATE;
            }
        }

        $prop->setValue($event, $actions);
    }
}
