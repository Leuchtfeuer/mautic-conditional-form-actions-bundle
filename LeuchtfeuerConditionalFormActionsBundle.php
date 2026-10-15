<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle;

use Mautic\IntegrationsBundle\Bundle\AbstractPluginBundle;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\DependencyInjection\Compiler\ReplaceSubmissionModelDispatcherPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class LeuchtfeuerConditionalFormActionsBundle extends AbstractPluginBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new ReplaceSubmissionModelDispatcherPass());
    }
}
