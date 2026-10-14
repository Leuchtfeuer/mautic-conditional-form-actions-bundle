<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\EventDispatcher;

use Mautic\FormBundle\Event\SubmissionEvent;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity\FormActionExecutionLog;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity\FormActionExecutionLogRepository;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Service\ConditionsEvaluator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Wraps the dispatcher used by SubmissionModel so rejected conditional actions are
 * skipped without stopPropagation() (which would mute later actions and FORM_ON_SUBMIT).
 */
final readonly class ConditionalFormActionDispatcher implements EventDispatcherInterface
{
    public function __construct(
        // Lazy: avoids wrapper → event_dispatcher → subscribers → SubmissionModel → wrapper
        #[Autowire(service: 'event_dispatcher', lazy: true)]
        private EventDispatcherInterface $inner,
        private Config $pluginConfig,
        private ConditionsEvaluator $conditionsEvaluator,
        private FormActionExecutionLogRepository $logRepository,
    ) {
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        if (!$this->shouldGateAction($event)) {
            return $this->inner->dispatch($event, $eventName);
        }

        /** @var SubmissionEvent $event */
        $action = $event->getAction();
        if (null === $action) {
            return $this->inner->dispatch($event, $eventName);
        }

        $shouldExecute = $this->conditionsEvaluator->shouldExecute(
            $action,
            $event->getSubmission(),
            $event->getSubmission()->getLead()
        );

        if (!$shouldExecute) {
            $this->logRepository->logExecution(
                $event->getSubmission(),
                $action,
                false,
                FormActionExecutionLog::DETAILS_CONDITIONS_NOT_MET
            );

            return $event;
        }

        try {
            $result = $this->inner->dispatch($event, $eventName);
            $this->logRepository->logExecution(
                $event->getSubmission(),
                $action,
                true,
                FormActionExecutionLog::DETAILS_CONDITIONS_MET
            );

            return $result;
        } catch (\Throwable $e) {
            $this->logRepository->logExecution(
                $event->getSubmission(),
                $action,
                true,
                FormActionExecutionLog::DETAILS_ERROR
            );

            throw $e;
        }
    }

    public function addListener(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->inner->addListener($eventName, $listener, $priority);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->inner->addSubscriber($subscriber);
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        $this->inner->removeListener($eventName, $listener);
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->inner->removeSubscriber($subscriber);
    }

    public function getListeners(?string $eventName = null): array
    {
        return $this->inner->getListeners($eventName);
    }

    public function getListenerPriority(string $eventName, callable $listener): ?int
    {
        return $this->inner->getListenerPriority($eventName, $listener);
    }

    public function hasListeners(?string $eventName = null): bool
    {
        return $this->inner->hasListeners($eventName);
    }

    private function shouldGateAction(object $event): bool
    {
        if (!$this->pluginConfig->isPublished()) {
            return false;
        }

        if (!$event instanceof SubmissionEvent) {
            return false;
        }

        return null !== $event->getAction();
    }
}
