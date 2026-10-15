<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Tests\Unit\Service;

use Mautic\FormBundle\Entity\Action;
use Mautic\FormBundle\Entity\Submission;
use Mautic\LeadBundle\Entity\CompanyLeadRepository;
use Mautic\LeadBundle\Segment\OperatorOptions;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity\FormActionCondition;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity\FormActionConditionRepository;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Service\ConditionsEvaluator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ConditionsEvaluatorTest extends TestCase
{
    /** @var FormActionConditionRepository&MockObject */
    private MockObject $conditionRepository;

    /** @var CompanyLeadRepository&MockObject */
    private MockObject $companyLeadRepository;

    private ConditionsEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->conditionRepository   = $this->createMock(FormActionConditionRepository::class);
        $this->companyLeadRepository = $this->createMock(CompanyLeadRepository::class);
        $this->evaluator             = new ConditionsEvaluator(
            $this->conditionRepository,
            $this->companyLeadRepository,
        );
    }

    public function testFormOnlyConditionCanPassWithoutLead(): void
    {
        $action     = $this->createMock(Action::class);
        $submission = $this->createMock(Submission::class);

        $action->method('getId')->willReturn(1);
        $submission->method('getResults')->willReturn(['source' => 'newsletter']);

        $conditionEntity = $this->createMock(FormActionCondition::class);
        $conditionEntity->method('getConditions')->willReturn([
            [
                'glue'       => 'and',
                'field'      => 'source',
                'object'     => 'form',
                'operator'   => OperatorOptions::EQUAL_TO,
                'properties' => ['filter' => 'newsletter'],
            ],
        ]);

        $this->conditionRepository->method('findByActionId')->with(1)->willReturn($conditionEntity);
        $this->companyLeadRepository->expects($this->never())->method('getPrimaryCompanyByLeadId');

        $this->assertTrue($this->evaluator->shouldExecute($action, $submission, null));
    }

    public function testFormOnlyConditionCanFailWithoutLead(): void
    {
        $action     = $this->createMock(Action::class);
        $submission = $this->createMock(Submission::class);

        $action->method('getId')->willReturn(1);
        $submission->method('getResults')->willReturn(['source' => 'other']);

        $conditionEntity = $this->createMock(FormActionCondition::class);
        $conditionEntity->method('getConditions')->willReturn([
            [
                'glue'       => 'and',
                'field'      => 'source',
                'object'     => 'form',
                'operator'   => OperatorOptions::EQUAL_TO,
                'properties' => ['filter' => 'newsletter'],
            ],
        ]);

        $this->conditionRepository->method('findByActionId')->with(1)->willReturn($conditionEntity);

        $this->assertFalse($this->evaluator->shouldExecute($action, $submission, null));
    }

    public function testLeadConditionFailsWithoutLead(): void
    {
        $action     = $this->createMock(Action::class);
        $submission = $this->createMock(Submission::class);

        $action->method('getId')->willReturn(1);
        $submission->method('getResults')->willReturn([]);

        $conditionEntity = $this->createMock(FormActionCondition::class);
        $conditionEntity->method('getConditions')->willReturn([
            [
                'glue'       => 'and',
                'field'      => 'country',
                'object'     => 'lead',
                'operator'   => OperatorOptions::EQUAL_TO,
                'properties' => ['filter' => 'Poland'],
            ],
        ]);

        $this->conditionRepository->method('findByActionId')->with(1)->willReturn($conditionEntity);

        $this->assertFalse($this->evaluator->shouldExecute($action, $submission, null));
    }
}
