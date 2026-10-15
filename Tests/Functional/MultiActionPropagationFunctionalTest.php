<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\FormBundle\Entity\Action;
use Mautic\FormBundle\Entity\Submission;
use Mautic\FormBundle\Entity\SubmissionRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Segment\OperatorOptions;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity\FormActionCondition;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity\FormActionExecutionLog;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity\FormActionExecutionLogRepository;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Tests\Fixtures\FunctionalFixtureHelper;
use Symfony\Component\HttpFoundation\Request;

/**
 * Ensures a skipped conditional action does not stop later actions or FORM_ON_SUBMIT.
 */
final class MultiActionPropagationFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    private FunctionalFixtureHelper $fixtureHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureHelper = new FunctionalFixtureHelper($this->em, $this->client);
        $this->fixtureHelper->createAndEnablePlugin();
    }

    public function testLaterActionStillRunsWhenEarlierActionConditionsFail(): void
    {
        $skippedSegment  = $this->fixtureHelper->createSegment('Skipped Segment', 'skipped-segment');
        $executedSegment = $this->fixtureHelper->createSegment('Executed Segment', 'executed-segment');

        $contact = new Lead();
        $contact->setEmail('multi-action@test.com');
        $contact->setCountry('Germany');
        $this->em->persist($contact);
        $this->em->flush();

        $form = $this->fixtureHelper->createComplexFormViaApi('Multi Action Propagation Form', [
            [
                'name'        => 'Add to skipped segment',
                'description' => 'Should be skipped when country is not Poland',
                'type'        => 'lead.changelist',
                'order'       => 1,
                'properties'  => [
                    'addToLists'      => [$skippedSegment->getId()],
                    'removeFromLists' => [],
                ],
            ],
            [
                'name'        => 'Add to executed segment',
                'description' => 'Should always run (no conditions)',
                'type'        => 'lead.changelist',
                'order'       => 2,
                'properties'  => [
                    'addToLists'      => [$executedSegment->getId()],
                    'removeFromLists' => [],
                ],
            ],
        ]);

        $actions = $form->getActions()->toArray();
        usort($actions, static fn (Action $a, Action $b): int => $a->getOrder() <=> $b->getOrder());

        $this->assertCount(2, $actions);
        $skippedAction  = $actions[0];
        $executedAction = $actions[1];

        $this->createFormActionCondition($skippedAction, [
            [
                'glue'       => 'and',
                'field'      => 'country',
                'object'     => 'lead',
                'operator'   => OperatorOptions::EQUAL_TO,
                'properties' => ['filter' => 'Poland'],
            ],
        ]);

        $crawler     = $this->client->request(Request::METHOD_GET, "/form/{$form->getId()}");
        $formCrawler = $crawler->filter('form[id=mauticform_multiactionpropagationform]');
        $this->assertCount(1, $formCrawler);

        $formElement = $formCrawler->form();
        $formElement->get('mauticform[email]')->setValue('multi-action@test.com');
        $this->client->submit($formElement);
        self::assertResponseIsSuccessful();

        $this->em->clear();

        /** @var Lead $updatedContact */
        $updatedContact = $this->em->getRepository(Lead::class)->findOneBy(['email' => 'multi-action@test.com']);
        $this->assertInstanceOf(Lead::class, $updatedContact);

        $leadLists = $this->em->getRepository(LeadList::class)->getLeadLists($updatedContact->getId());

        $this->assertArrayNotHasKey($skippedSegment->getId(), $leadLists, 'First action conditions failed; contact must not be in the skipped segment.');
        $this->assertArrayHasKey($executedSegment->getId(), $leadLists, 'Second action must still run after the first action was skipped.');

        $this->assertActionLog(
            $form->getId(),
            $skippedAction->getId(),
            false,
            FormActionExecutionLog::DETAILS_CONDITIONS_NOT_MET
        );
        $this->assertActionLog(
            $form->getId(),
            $executedAction->getId(),
            true,
            FormActionExecutionLog::DETAILS_CONDITIONS_MET
        );
    }

    /**
     * @param array<mixed> $conditions
     */
    private function createFormActionCondition(Action $action, array $conditions): void
    {
        $conditionEntity = new FormActionCondition();
        $conditionEntity->setAction($action);
        $conditionEntity->setConditions($conditions);

        $this->em->persist($conditionEntity);
        $this->em->flush();
    }

    private function assertActionLog(
        int $formId,
        int $actionId,
        bool $expectedExecuted,
        string $expectedDetails,
    ): void {
        /** @var SubmissionRepository $submissionRepo */
        $submissionRepo = $this->em->getRepository(Submission::class);
        $submission     = $submissionRepo->findOneBy(['form' => $formId], ['dateSubmitted' => 'DESC']);
        $this->assertInstanceOf(Submission::class, $submission);

        /** @var FormActionExecutionLogRepository $logRepo */
        $logRepo = $this->em->getRepository(FormActionExecutionLog::class);

        /** @var FormActionExecutionLog|null $log */
        $log = $logRepo->findOneBy([
            'submission' => $submission,
            'action'     => $actionId,
        ]);

        $this->assertInstanceOf(FormActionExecutionLog::class, $log);
        $this->assertSame($expectedExecuted, $log->isExecuted());
        $this->assertSame($expectedDetails, $log->getLogDetails());
    }
}
