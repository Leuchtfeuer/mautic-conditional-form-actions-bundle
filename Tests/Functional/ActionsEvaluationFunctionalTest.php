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
use PHPUnit\Framework\Assert;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;

class ActionsEvaluationFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    private FunctionalFixtureHelper $fixtureHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureHelper = new FunctionalFixtureHelper($this->em, $this->client);
        $this->fixtureHelper->createAndEnablePlugin();
    }

    /**
     * @dataProvider conditionsDataProvider
     *
     * @param array<mixed>|null    $conditions    The conditions definition for the DB
     * @param array<string, mixed> $leadData      Profile data to pre-fill on the contact
     * @param array<string, mixed> $companyData   Company data (if any)
     * @param array<string, mixed> $formData      The submitted form values
     * @param bool                 $shouldExecute Whether we expect the action (Add to Segment) to run
     */
    public function testConditionalActionExecution(
        ?array $conditions,
        array $leadData,
        array $companyData,
        array $formData,
        bool $shouldExecute
    ): void {
        // 1. Preparation: Create Segment (Target of the action)
        $segment = $this->fixtureHelper->createSegment('Target Segment', 'target-segment');

        // 2. Preparation: Create Contact & Company context
        $contact = new Lead();
        $contact->setEmail($formData['email']);
        if (!empty($leadData)) {
            foreach ($leadData as $field => $value) {
                $this->setEntityValue($contact, $field, $value);
            }
        }
        $this->em->persist($contact);
        $this->em->flush();

        if (!empty($companyData)) {
            $company = $this->fixtureHelper->createCompany($companyData['companyname'] ?? 'Test Corp');
            foreach ($companyData as $field => $value) {
                $this->setEntityValue($company, $field, $value);
            }
            $this->em->persist($company);
            $this->fixtureHelper->addContactToCompany($contact, $company);
        }
        $this->em->flush();

        // 3. Create Form via Helper with "Modify Contact's Segment"
        $form = $this->fixtureHelper->createComplexFormViaApi('Conditions Test Form', [
            [
                'name'        => 'Modify Contact\'s Segment',
                'description' => 'action description',
                'type'        => 'lead.changelist',
                'order'       => 1,
                'properties'  => [
                    'addToLists'      => [$segment->getId()],
                    'removeFromLists' => [],
                ],
            ],
        ]);

        $targetAction = $form->getActions()->first();

        // 5. Attach Conditions to the Action
        if (null !== $conditions) {
            $this->createFormActionCondition($targetAction, $conditions);
        }

        // 6. Execution: Submit the Form
        $crawler     = $this->client->request(Request::METHOD_GET, "/form/{$form->getId()}");
        $formCrawler = $crawler->filter('form[id=mauticform_conditionstestform]');
        $formElement = $formCrawler->form();

        $mauticFormValues = [];
        foreach ($formData as $key => $value) {
            if (is_array($value)) {
                $multiselectFieldName = "mauticform[{$key}]";
                if ($formElement->has($multiselectFieldName)) {
                    /** @var ChoiceFormField $field */
                    $field = $formElement->get($multiselectFieldName);
                    if (is_object($field) && method_exists($field, 'setValue')) {
                        $field->setValue($value);
                    } else {
                        $this->handleCheckboxValues($formElement, $key, $value);
                    }
                } else {
                    $this->handleCheckboxValues($formElement, $key, $value);
                }
            } else {
                $fieldName = "mauticform[{$key}]";
                if ($formElement->has($fieldName)) {
                    $formElement->get($fieldName)->setValue($value);
                }
            }
        }

        $formElement->setValues($mauticFormValues);
        $this->client->submit($formElement);
        Assert::assertTrue($this->client->getResponse()->isOk());

        // 7. Assertion
        $this->em->clear(); // Clear Doctrine identity map

        /** @var Lead $updatedContact */
        $updatedContact = $this->em->getRepository(Lead::class)->findOneBy(['email' => $contact->getEmail()]);
        $leadLists      = $this->em->getRepository(LeadList::class)->getLeadLists($updatedContact->getId());

        $isInSegment = isset($leadLists[$segment->getId()]);

        if ($shouldExecute) {
            Assert::assertTrue($isInSegment, 'The Action should have executed, but the contact is NOT in the segment.');
        } else {
            Assert::assertFalse($isInSegment, 'The Action should have been SKIPPED, but the contact IS in the segment.');
        }

        // 8. Check Logs
        $this->checkLog($form->getId(), $targetAction->getId(), $shouldExecute);
    }

    /**
     * Verifies that a log entry was created for the specific action execution.
     */
    private function checkLog(int $formId, int $actionId, bool $expectedExecutionStatus): void
    {
        /** @var SubmissionRepository $submissionRepo */
        $submissionRepo = $this->em->getRepository(Submission::class);

        // Find the most recent submission for this form
        $submission = $submissionRepo->findOneBy(['form' => $formId], ['dateSubmitted' => 'DESC']);
        Assert::assertNotNull($submission, 'No submission found for log verification.');

        /** @var FormActionExecutionLogRepository $logRepo */
        $logRepo = $this->em->getRepository(FormActionExecutionLog::class);

        /** @var FormActionExecutionLog|null $log */
        $log = $logRepo->findOneBy([
            'submission' => $submission,
            'action'     => $actionId,
        ]);

        Assert::assertNotNull($log, 'No execution log found for this action/submission combination.');

        Assert::assertEquals(
            $expectedExecutionStatus,
            $log->isExecuted(),
            sprintf('Log status mismatch. Expected isExecuted: %s, but got: %s',
                $expectedExecutionStatus ? 'true' : 'false',
                $log->isExecuted() ? 'true' : 'false'
            )
        );

        $expectedDetails = $expectedExecutionStatus
            ? FormActionExecutionLog::DETAILS_CONDITIONS_MET
            : FormActionExecutionLog::DETAILS_CONDITIONS_NOT_MET;

        Assert::assertEquals($expectedDetails, $log->getLogDetails(), 'Log detail message is incorrect.');
    }

    private function setEntityValue(object $entity, string $field, mixed $value): void
    {
        $method = 'set'.ucfirst($field);
        if (method_exists($entity, $method)) {
            $entity->$method($value);
        }
    }

    /**
     * @param array<int, string> $values
     */
    private function handleCheckboxValues(Form $formElement, string $key, array $values): void
    {
        $allFields = $formElement->all();

        /**
         * @var ChoiceFormField $field
         */
        foreach ($allFields as $fieldName => $field) {
            // Match checkbox fields for this key (e.g., mauticform[interests][0], mauticform[interests][1])
            if (preg_match("/^mauticform\[{$key}\]\[\d+\]$/", $fieldName)) {
                // Check if this checkbox's value is in our desired values array
                if (is_object($field) && method_exists($field, 'availableOptionValues')) {
                    $checkboxValue = $field->availableOptionValues()[0] ?? null;
                    if ($checkboxValue && in_array($checkboxValue, $values, true)) {
                        $field->tick();
                    }
                }
            }
        }
    }

    /**
     * Helper to persist the condition entity.
     *
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

    public static function conditionsDataProvider(): \Generator
    {
        // ---------------------------------------------------------------------------------
        // 1. NO CONDITIONS
        // ---------------------------------------------------------------------------------
        yield 'No conditions defined -> Action Always Executes' => [
            'conditions'    => null,
            'leadData'      => [],
            'companyData'   => [],
            'formData'      => ['email' => 'nocondition@test.com'],
            'shouldExecute' => true,
        ];

        yield 'Empty conditions array -> Action Always Executes' => [
            'conditions'    => [],
            'leadData'      => [],
            'companyData'   => [],
            'formData'      => ['email' => 'emptycondition@test.com'],
            'shouldExecute' => true,
        ];

        // ---------------------------------------------------------------------------------
        // 2. LEAD FIELD CONDITIONS
        // ---------------------------------------------------------------------------------
        yield 'Lead Condition: Country equals Poland (Match)' => [
            'conditions' => [
                [
                    'glue'       => 'and',
                    'field'      => 'country',
                    'object'     => 'lead',
                    'operator'   => OperatorOptions::EQUAL_TO,
                    'properties' => ['filter' => 'Poland'],
                ],
            ],
            'leadData'      => ['country' => 'Poland'],
            'companyData'   => [],
            'formData'      => ['email' => 'pl@test.com'],
            'shouldExecute' => true,
        ];

        yield 'Lead Condition: Country equals Poland (No Match)' => [
            'conditions' => [
                [
                    'glue'       => 'and',
                    'field'      => 'country',
                    'object'     => 'lead',
                    'operator'   => OperatorOptions::EQUAL_TO,
                    'properties' => ['filter' => 'Poland'],
                ],
            ],
            'leadData'      => ['country' => 'Germany'],
            'companyData'   => [],
            'formData'      => ['email' => 'germany@test.com'],
            'shouldExecute' => false,
        ];

        // ---------------------------------------------------------------------------------
        // 3. FORM FIELD CONDITIONS
        // ---------------------------------------------------------------------------------
        yield 'Form Condition: Source equals "WebSearch" (Match)' => [
            'conditions' => [
                [
                    'glue'       => 'and',
                    'field'      => 'source', // 'source' is a field in the complex form fixture
                    'object'     => 'form',
                    'operator'   => OperatorOptions::EQUAL_TO,
                    'properties' => ['filter' => 'WebSearch'],
                ],
            ],
            'leadData'      => [],
            'companyData'   => [],
            'formData'      => ['email' => 'websearch@test.com', 'source' => 'WebSearch'],
            'shouldExecute' => true,
        ];

        yield 'Form Condition: Source equals "WebSearch" (No Match)' => [
            'conditions' => [
                [
                    'glue'       => 'and',
                    'field'      => 'source',
                    'object'     => 'form',
                    'operator'   => OperatorOptions::EQUAL_TO,
                    'properties' => ['filter' => 'WebSearch'],
                ],
            ],
            'leadData'      => [],
            'companyData'   => [],
            'formData'      => ['email' => 'direct@test.com', 'source' => 'Direct'],
            'shouldExecute' => false,
        ];

        yield 'Form Condition: Select Box IN array (Match)' => [
            'conditions' => [
                [
                    'glue'       => 'and',
                    'field'      => 'colors', // Select box in complex form
                    'object'     => 'form',
                    'operator'   => OperatorOptions::IN,
                    'properties' => ['filter' => ['red', 'blue']],
                ],
            ],
            'leadData'      => [],
            'companyData'   => [],
            'formData'      => ['email' => 'colors@test.com', 'colors' => 'blue'],
            'shouldExecute' => true,
        ];

        // ---------------------------------------------------------------------------------
        // 4. COMPANY FIELD CONDITIONS
        // ---------------------------------------------------------------------------------
        yield 'Company Condition: Name contains "Leuchtfeuer" (Match)' => [
            'conditions' => [
                [
                    'glue'       => 'and',
                    'field'      => 'companyname',
                    'object'     => 'company',
                    'operator'   => OperatorOptions::CONTAINS,
                    'properties' => ['filter' => 'Leuchtfeuer'],
                ],
            ],
            'leadData'      => [],
            'companyData'   => ['companyname' => 'Leuchtfeuer Digital Marketing'],
            'formData'      => ['email' => 'ceo@leuchtfeuer.com', 'companyname' => 'Leuchtfeuer Digital Marketing'],
            'shouldExecute' => true,
        ];

        yield 'Company Condition: Name contains "Leuchtfeuer" (No Match - Different Company)' => [
            'conditions' => [
                [
                    'glue'       => 'and',
                    'field'      => 'companyname',
                    'object'     => 'company',
                    'operator'   => OperatorOptions::CONTAINS,
                    'properties' => ['filter' => 'Leuchtfeuer'],
                ],
            ],
            'leadData'      => [],
            'companyData'   => ['companyname' => 'Acme Corp'],
            'formData'      => ['email' => 'ceo@acme.com', 'companyname' => 'Acme Corp'],
            'shouldExecute' => false,
        ];

        // ---------------------------------------------------------------------------------
        // 5. MIXED CONDITIONS (AND/OR)
        // ---------------------------------------------------------------------------------
        yield 'Mixed: Form Source "Ads" AND Lead Country "Germany" (Both Match)' => [
            'conditions' => [
                [
                    'glue'       => 'and',
                    'field'      => 'source',
                    'object'     => 'form',
                    'operator'   => OperatorOptions::EQUAL_TO,
                    'properties' => ['filter' => 'Ads'],
                ],
                [
                    'glue'       => 'and',
                    'field'      => 'country',
                    'object'     => 'lead',
                    'operator'   => OperatorOptions::EQUAL_TO,
                    'properties' => ['filter' => 'Germany'],
                ],
            ],
            'leadData'      => ['country' => 'Germany'],
            'companyData'   => [],
            'formData'      => ['email' => 'ads@germany.com', 'source' => 'Ads'],
            'shouldExecute' => true,
        ];

        yield 'Mixed: Form Source "Ads" AND Lead Country "Germany" (One Failed)' => [
            'conditions' => [
                [
                    'glue'       => 'and',
                    'field'      => 'source',
                    'object'     => 'form',
                    'operator'   => OperatorOptions::EQUAL_TO,
                    'properties' => ['filter' => 'Ads'],
                ],
                [
                    'glue'       => 'and',
                    'field'      => 'country',
                    'object'     => 'lead',
                    'operator'   => OperatorOptions::EQUAL_TO,
                    'properties' => ['filter' => 'Germany'],
                ],
            ],
            'leadData'      => ['country' => 'United Kingdom'], // Mismatch
            'companyData'   => [],
            'formData'      => ['email' => 'ads@uk.com', 'source' => 'Ads'],
            'shouldExecute' => false,
        ];

        yield 'Mixed: Form field OR Lead field (Match second condition)' => [
            'conditions' => [
                [
                    'glue'       => 'and', // First item glue is ignored usually, but kept for structure
                    'field'      => 'source',
                    'object'     => 'form',
                    'operator'   => OperatorOptions::EQUAL_TO,
                    'properties' => ['filter' => 'Partner'],
                ],
                [
                    'glue'       => 'or',
                    'field'      => 'country',
                    'object'     => 'lead',
                    'operator'   => OperatorOptions::EQUAL_TO,
                    'properties' => ['filter' => 'Germany'],
                ],
            ],
            'leadData'      => ['country' => 'Germany'], // Matches OR
            'companyData'   => [],
            'formData'      => ['email' => 'or@test.com', 'source' => 'Web'], // Mismatch first
            'shouldExecute' => true,
        ];
    }
}
