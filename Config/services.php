<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;
use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use Mautic\FormBundle\Model\SubmissionModel;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Model\SubmissionModelDecorated;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $excludes = [];

    $services->load('MauticPlugin\\LeuchtfeuerConditionalFormActionsBundle\\Entity\\', '../Entity/*Repository.php')
        ->tag(ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG);

    $services->load('MauticPlugin\\LeuchtfeuerConditionalFormActionsBundle\\', '../')
        ->exclude('../{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, $excludes)).'}');

    $services->get(MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Integration\LeuchtfeuerConditionalFormActionsIntegration::class)
        ->tag('mautic.integration')
        ->tag('mautic.basic_integration');
    $services->get(MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Integration\Support\ConfigSupport::class)
        ->tag('mautic.config_integration');

    $services->alias('mautic.integration.leuchtfeuerconditionalformactions', MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Integration\LeuchtfeuerConditionalFormActionsIntegration::class);

    $services->set(SubmissionModelDecorated::class)
        ->decorate(SubmissionModel::class);
};
