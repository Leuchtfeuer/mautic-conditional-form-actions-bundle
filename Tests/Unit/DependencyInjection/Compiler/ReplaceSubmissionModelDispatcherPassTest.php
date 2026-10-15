<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Tests\Unit\DependencyInjection\Compiler;

use Mautic\FormBundle\Model\SubmissionModel;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\DependencyInjection\Compiler\ReplaceSubmissionModelDispatcherPass;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\EventDispatcher\ConditionalFormActionDispatcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class ReplaceSubmissionModelDispatcherPassTest extends TestCase
{
    public function testReplacesSubmissionModelDispatcherArgument(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ConditionalFormActionDispatcher::class, new Definition(ConditionalFormActionDispatcher::class));
        $container->setDefinition(SubmissionModel::class, new Definition(SubmissionModel::class));

        (new ReplaceSubmissionModelDispatcherPass())->process($container);

        $argument = $container->getDefinition(SubmissionModel::class)->getArgument('$dispatcher');

        $this->assertInstanceOf(Reference::class, $argument);
        $this->assertSame(ConditionalFormActionDispatcher::class, (string) $argument);
    }

    public function testDoesNothingWhenSubmissionModelMissing(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ConditionalFormActionDispatcher::class, new Definition(ConditionalFormActionDispatcher::class));

        (new ReplaceSubmissionModelDispatcherPass())->process($container);

        $this->assertFalse($container->hasDefinition(SubmissionModel::class));
    }

    public function testDoesNothingWhenWrapperMissing(): void
    {
        $container = new ContainerBuilder();
        $definition = new Definition(SubmissionModel::class);
        $definition->setArgument('$dispatcher', new Reference('event_dispatcher'));
        $container->setDefinition(SubmissionModel::class, $definition);

        (new ReplaceSubmissionModelDispatcherPass())->process($container);

        $argument = $container->getDefinition(SubmissionModel::class)->getArgument('$dispatcher');
        $this->assertInstanceOf(Reference::class, $argument);
        $this->assertSame('event_dispatcher', (string) $argument);
    }
}
