<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\DependencyInjection\Compiler;

use Mautic\FormBundle\Model\SubmissionModel;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\EventDispatcher\ConditionalFormActionDispatcher;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Injects ConditionalFormActionDispatcher into SubmissionModel only (not the global dispatcher).
 */
final class ReplaceSubmissionModelDispatcherPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(SubmissionModel::class) && !$container->hasAlias(SubmissionModel::class)) {
            return;
        }

        if (!$container->hasDefinition(ConditionalFormActionDispatcher::class)) {
            return;
        }

        $container->findDefinition(SubmissionModel::class)
            ->setArgument('$dispatcher', new Reference(ConditionalFormActionDispatcher::class));
    }
}
