<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Tests\Unit\EventDispatcher;

use Mautic\FormBundle\Entity\Action;
use Mautic\FormBundle\Entity\Submission;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\FormEvents;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity\FormActionExecutionLog;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity\FormActionExecutionLogRepository;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\EventDispatcher\ConditionalFormActionDispatcher;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Service\ConditionsEvaluator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

final class ConditionalFormActionDispatcherTest extends TestCase
{
    /** @var EventDispatcherInterface&MockObject */
    private MockObject $inner;

    /** @var Config&MockObject */
    private MockObject $pluginConfig;

    /** @var ConditionsEvaluator&MockObject */
    private MockObject $conditionsEvaluator;

    /** @var FormActionExecutionLogRepository&MockObject */
    private MockObject $logRepository;

    private ConditionalFormActionDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->inner                 = $this->createMock(EventDispatcherInterface::class);
        $this->pluginConfig          = $this->createMock(Config::class);
        $this->conditionsEvaluator   = $this->createMock(ConditionsEvaluator::class);
        $this->logRepository         = $this->createMock(FormActionExecutionLogRepository::class);
        $this->dispatcher            = new ConditionalFormActionDispatcher(
            $this->inner,
            $this->pluginConfig,
            $this->conditionsEvaluator,
            $this->logRepository,
        );
    }

    public function testForwardsUnchangedWhenPluginUnpublished(): void
    {
        $event  = $this->createSubmissionEvent(withAction: true);
        $action = $event->getAction();
        $this->assertInstanceOf(Action::class, $action);

        $this->pluginConfig->method('isPublished')->willReturn(false);
        $this->inner->expects($this->once())
            ->method('dispatch')
            ->with($event, 'mautic.form.on_execute_submit_action')
            ->willReturn($event);
        $this->conditionsEvaluator->expects($this->never())->method('shouldExecute');
        $this->logRepository->expects($this->never())->method('logExecution');

        $this->assertSame($event, $this->dispatcher->dispatch($event, 'mautic.form.on_execute_submit_action'));
    }

    public function testForwardsFormOnSubmitWhenActionIsNull(): void
    {
        $event = $this->createSubmissionEvent(withAction: false);

        $this->pluginConfig->method('isPublished')->willReturn(true);
        $this->inner->expects($this->once())
            ->method('dispatch')
            ->with($event, FormEvents::FORM_ON_SUBMIT)
            ->willReturn($event);
        $this->conditionsEvaluator->expects($this->never())->method('shouldExecute');
        $this->logRepository->expects($this->never())->method('logExecution');

        $this->assertSame($event, $this->dispatcher->dispatch($event, FormEvents::FORM_ON_SUBMIT));
    }

    public function testForwardsNonSubmissionEventsUnchanged(): void
    {
        $event = new \stdClass();

        $this->pluginConfig->method('isPublished')->willReturn(true);
        $this->inner->expects($this->once())
            ->method('dispatch')
            ->with($event, 'other.event')
            ->willReturn($event);
        $this->conditionsEvaluator->expects($this->never())->method('shouldExecute');

        $this->assertSame($event, $this->dispatcher->dispatch($event, 'other.event'));
    }

    public function testSkipsDispatchAndLogsWhenConditionsNotMet(): void
    {
        $event  = $this->createSubmissionEvent(withAction: true);
        $action = $event->getAction();
        $lead   = $event->getSubmission()->getLead();
        $this->assertInstanceOf(Action::class, $action);
        $this->assertInstanceOf(Lead::class, $lead);

        $this->pluginConfig->method('isPublished')->willReturn(true);
        $this->conditionsEvaluator->expects($this->once())
            ->method('shouldExecute')
            ->with($action, $event->getSubmission(), $lead)
            ->willReturn(false);
        $this->inner->expects($this->never())->method('dispatch');
        $this->logRepository->expects($this->once())
            ->method('logExecution')
            ->with(
                $event->getSubmission(),
                $action,
                false,
                FormActionExecutionLog::DETAILS_CONDITIONS_NOT_MET
            );

        $this->assertSame($event, $this->dispatcher->dispatch($event, 'mautic.form.on_execute_submit_action'));
        $this->assertFalse($event->isPropagationStopped());
    }

    public function testForwardsAndLogsWhenConditionsMet(): void
    {
        $event  = $this->createSubmissionEvent(withAction: true);
        $action = $event->getAction();
        $lead   = $event->getSubmission()->getLead();
        $this->assertInstanceOf(Action::class, $action);
        $this->assertInstanceOf(Lead::class, $lead);

        $this->pluginConfig->method('isPublished')->willReturn(true);
        $this->conditionsEvaluator->method('shouldExecute')->willReturn(true);
        $this->inner->expects($this->once())
            ->method('dispatch')
            ->with($event, 'mautic.form.on_execute_submit_action')
            ->willReturn($event);
        $this->logRepository->expects($this->once())
            ->method('logExecution')
            ->with(
                $event->getSubmission(),
                $action,
                true,
                FormActionExecutionLog::DETAILS_CONDITIONS_MET
            );

        $this->assertSame($event, $this->dispatcher->dispatch($event, 'mautic.form.on_execute_submit_action'));
    }

    public function testLogsErrorAndRethrowsWhenInnerDispatchFails(): void
    {
        $event  = $this->createSubmissionEvent(withAction: true);
        $action = $event->getAction();
        $this->assertInstanceOf(Action::class, $action);

        $this->pluginConfig->method('isPublished')->willReturn(true);
        $this->conditionsEvaluator->method('shouldExecute')->willReturn(true);
        $this->inner->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('boom'));
        $this->logRepository->expects($this->once())
            ->method('logExecution')
            ->with(
                $event->getSubmission(),
                $action,
                true,
                FormActionExecutionLog::DETAILS_ERROR
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $this->dispatcher->dispatch($event, 'mautic.form.on_execute_submit_action');
    }

    public function testEvaluatesConditionsWhenSubmissionHasNoLead(): void
    {
        $event  = $this->createSubmissionEvent(withAction: true, withLead: false);
        $action = $event->getAction();
        $this->assertInstanceOf(Action::class, $action);

        $this->pluginConfig->method('isPublished')->willReturn(true);
        $this->conditionsEvaluator->expects($this->once())
            ->method('shouldExecute')
            ->with($action, $event->getSubmission(), null)
            ->willReturn(false);
        $this->inner->expects($this->never())->method('dispatch');
        $this->logRepository->expects($this->once())
            ->method('logExecution')
            ->with(
                $event->getSubmission(),
                $action,
                false,
                FormActionExecutionLog::DETAILS_CONDITIONS_NOT_MET
            );

        $this->assertSame($event, $this->dispatcher->dispatch($event, 'mautic.form.on_execute_submit_action'));
    }

    public function testForwardsWhenSubmissionHasNoLeadAndConditionsMet(): void
    {
        $event  = $this->createSubmissionEvent(withAction: true, withLead: false);
        $action = $event->getAction();
        $this->assertInstanceOf(Action::class, $action);

        $this->pluginConfig->method('isPublished')->willReturn(true);
        $this->conditionsEvaluator->expects($this->once())
            ->method('shouldExecute')
            ->with($action, $event->getSubmission(), null)
            ->willReturn(true);
        $this->inner->expects($this->once())
            ->method('dispatch')
            ->with($event, 'mautic.form.on_execute_submit_action')
            ->willReturn($event);
        $this->logRepository->expects($this->once())
            ->method('logExecution')
            ->with(
                $event->getSubmission(),
                $action,
                true,
                FormActionExecutionLog::DETAILS_CONDITIONS_MET
            );

        $this->assertSame($event, $this->dispatcher->dispatch($event, 'mautic.form.on_execute_submit_action'));
    }

    public function testDelegatesHasListenersToInner(): void
    {
        $this->inner->expects($this->once())
            ->method('hasListeners')
            ->with(FormEvents::FORM_ON_SUBMIT)
            ->willReturn(true);

        $this->assertTrue($this->dispatcher->hasListeners(FormEvents::FORM_ON_SUBMIT));
    }

    private function createSubmissionEvent(bool $withAction, bool $withLead = true): SubmissionEvent
    {
        $lead = $withLead ? $this->createMock(Lead::class) : null;

        $submission = $this->createMock(Submission::class);
        $submission->method('getLead')->willReturn($lead);

        $event = new SubmissionEvent($submission, [], [], new Request());

        if ($withAction) {
            $action = $this->createMock(Action::class);
            $action->method('getType')->willReturn('lead.changelist');
            $event->setAction($action);
        }

        return $event;
    }
}
