<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\EventListener;

use Mautic\PluginBundle\Bundle\PluginDatabase;
use Mautic\PluginBundle\Event\PluginInstallEvent;
use Mautic\PluginBundle\PluginEvents;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Integration\LeuchtfeuerConditionalFormActionsIntegration;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PluginInstallSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly PluginDatabase $pluginDatabase)
    {
    }

    public function onInstall(PluginInstallEvent $event): void
    {
        if (!$event->checkContext(LeuchtfeuerConditionalFormActionsIntegration::DISPLAY_NAME)) {
            return;
        }

        // Run DB migrations instead of automatic schema update
        $this->pluginDatabase->onPluginUpdate($event->getPlugin());

        // Prevent core PluginSubscriber from trying to create schema
        $event->stopPropagation();
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::ON_PLUGIN_INSTALL => ['onInstall', 10],
        ];
    }
}
