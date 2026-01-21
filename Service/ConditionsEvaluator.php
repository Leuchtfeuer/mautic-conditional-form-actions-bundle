<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Service;

use Mautic\FormBundle\Entity\Action;
use Mautic\FormBundle\Entity\Submission;
use Mautic\LeadBundle\Entity\CompanyLeadRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Exception\PrimaryCompanyNotFoundException;
use Mautic\LeadBundle\Segment\OperatorOptions;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity\FormActionConditionRepository;

class ConditionsEvaluator
{
    public function __construct(
        private FormActionConditionRepository $formActionConditionRepository,
        private CompanyLeadRepository $companyLeadRepository,
    ) {
    }

    public function shouldExecute(Action $action, Submission $submission, Lead $contact): bool
    {
        $conditionEntity = $this->formActionConditionRepository->findByActionId($action->getId());

        if (null === $conditionEntity) {
            // execute the action if there are no conditions
            return true;
        }

        $conditions     = $conditionEntity->getConditions();
        $leadArray      = $contact->getProfileFields();

        try {
            $companyArray = $this->companyLeadRepository->getPrimaryCompanyByLeadId($contact->getId());
        } catch (PrimaryCompanyNotFoundException) {
            $companyArray = null;
        }

        $formArray = $submission->getResults();
        if (empty($conditions)) {
            return true;
        }

        $dataSources = [
            'lead'    => $leadArray,
            'company' => $companyArray,
            'form'    => $formArray,
        ];

        $finalResult = false;
        $isFirstRule = true;

        foreach ($conditions as $rule) {
            $objectType = $rule['object'] ?? null;
            $dataSource = $dataSources[$objectType] ?? null;

            $currentResult = false; // Default to false if a rule cannot be evaluated
            if (null !== $dataSource && isset($rule['field'], $rule['operator'])) {
                $field       = $rule['field'];
                $actualValue = $dataSource[$field] ?? null;
                $filterValue = $rule['properties']['filter'] ?? null;
                $operator    = $rule['operator'];

                $currentResult = $this->evaluateSingleCondition($operator, $actualValue, $filterValue);
            }

            if ($isFirstRule) {
                $finalResult = $currentResult;
                $isFirstRule = false;
            } else {
                $glue = $rule['glue'] ?? 'and'; // Default to 'and'
                if ('and' === $glue) {
                    $finalResult = $finalResult && $currentResult;
                } elseif ('or' === $glue) {
                    $finalResult = $finalResult || $currentResult;
                }
            }
        }

        return $finalResult;
    }

    /**
     * Evaluates a single condition.
     *
     * @param string $operator    The comparison operator
     * @param mixed  $actualValue The value from the contact/company/form
     * @param mixed  $filterValue The value from the rule to compare against
     */
    private function evaluateSingleCondition(string $operator, mixed $actualValue, mixed $filterValue): bool
    {
        switch ($operator) {
            case OperatorOptions::EQUAL_TO:
                return mb_strtolower((string) $actualValue) === mb_strtolower((string) $filterValue);

            case OperatorOptions::NOT_EQUAL_TO:
                return mb_strtolower((string) $actualValue) !== mb_strtolower((string) $filterValue);

            case OperatorOptions::GREATER_THAN:
                if (!is_numeric($actualValue) || !is_numeric($filterValue)) {
                    return false;
                }

                return (float) $actualValue > (float) $filterValue;

            case OperatorOptions::GREATER_THAN_OR_EQUAL:
                if (!is_numeric($actualValue) || !is_numeric($filterValue)) {
                    return false;
                }

                return (float) $actualValue >= (float) $filterValue;

            case OperatorOptions::LESS_THAN:
                if (!is_numeric($actualValue) || !is_numeric($filterValue)) {
                    return false;
                }

                return (float) $actualValue < (float) $filterValue;

            case OperatorOptions::LESS_THAN_OR_EQUAL:
                if (!is_numeric($actualValue) || !is_numeric($filterValue)) {
                    return false;
                }

                return (float) $actualValue <= (float) $filterValue;

            case OperatorOptions::EMPTY:
                return empty($actualValue);

            case OperatorOptions::NOT_EMPTY:
                return !empty($actualValue);

            // @phpstan-ignore-next-line operator deprecated in M7, replacement is not available in M6
            case OperatorOptions::IN:
                if (!is_array($filterValue)) {
                    return false;
                }
                // Handle single values and comma-separated string values (from checkboxgrp)
                $actualValues = is_array($actualValue) ? $actualValue : explode(',', (string) $actualValue);

                // Normalize both arrays for case-insensitive comparison
                $actualValuesLower = array_map('mb_strtolower', array_map('trim', $actualValues));
                $filterValuesLower = array_map('mb_strtolower', array_map('trim', $filterValue));

                // Return true if any of the actual values are in the filter list
                return !empty(array_intersect($actualValuesLower, $filterValuesLower));

            // @phpstan-ignore-next-line operator deprecated in M7, replacement is not available in M6
            case OperatorOptions::NOT_IN:
                if (!is_array($filterValue)) {
                    return true;
                }
                // Handle single values and comma-separated string values
                $actualValues = is_array($actualValue) ? $actualValue : explode(',', (string) $actualValue);

                $actualValuesLower = array_map('mb_strtolower', array_map('trim', $actualValues));
                $filterValuesLower = array_map('mb_strtolower', array_map('trim', $filterValue));

                // Return true if NONE of the actual values are in the filter list
                return empty(array_intersect($actualValuesLower, $filterValuesLower));

            case OperatorOptions::REGEXP:
                $pattern = (string) $filterValue;
                $subject = (string) $actualValue;

                return '' !== $pattern && 1 === @preg_match($pattern, $subject);

            case OperatorOptions::NOT_REGEXP:
                $pattern = (string) $filterValue;
                if ('' === $pattern) {
                    return true; // Does not match an empty (invalid) pattern
                }
                $subject = (string) $actualValue;

                return 1 !== @preg_match($pattern, $subject);

            case OperatorOptions::STARTS_WITH:
                $search  = (string) $filterValue;
                $subject = (string) $actualValue;

                return '' !== $search && str_starts_with(mb_strtolower($subject), mb_strtolower($search));

            case OperatorOptions::ENDS_WITH:
                $search  = (string) $filterValue;
                $subject = (string) $actualValue;

                return '' !== $search && str_ends_with(mb_strtolower($subject), mb_strtolower($search));

            case OperatorOptions::CONTAINS:
                $search  = (string) $filterValue;
                $subject = (string) $actualValue;

                return '' !== $search && str_contains(mb_strtolower($subject), mb_strtolower($search));

            case OperatorOptions::LIKE:
                $filterString = (string) $filterValue;
                $actualString = (string) $actualValue;
                $pattern      = '/^'.str_replace('%', '.*', preg_quote($filterString, '/')).'$/i';

                return (bool) @preg_match($pattern, $actualString);

            case OperatorOptions::NOT_LIKE:
                $filterString = (string) $filterValue;
                $actualString = (string) $actualValue;
                $pattern      = '/^'.str_replace('%', '.*', preg_quote($filterString, '/')).'$/i';

                return !@preg_match($pattern, $actualString);

            default:
                return false;
        }
    }
}
