<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $excludes = [];

    $services->load('MauticPlugin\\LeuchtfeuerConditionalFormActionsBundle\\', '../')
        ->exclude('../{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, $excludes)).'}');

    $services->get(MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Integration\LeuchtfeuerConditionalFormActionsIntegration::class)
        ->tag('mautic.integration')
        ->tag('mautic.basic_integration');
    $services->get(MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Integration\Support\ConfigSupport::class)
        ->tag('mautic.config_integration');

    $services->alias('mautic.integration.leuchtfeuer_conditional_form_actions', MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Integration\LeuchtfeuerConditionalFormActionsIntegration::class);
};
