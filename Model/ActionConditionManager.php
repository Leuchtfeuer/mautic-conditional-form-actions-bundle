<?php

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\FormBundle\Entity\Action;
use Mautic\FormBundle\Entity\Form;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity\FormActionCondition;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity\FormActionConditionRepository;

class ActionConditionManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FormActionConditionRepository $repository,
    ) {
    }

    /**
     * @return array<int, FormActionCondition>
     */
    public function getFormActionConditions(Form $form): array
    {
        return $this->repository->findByFormId($form->getId());
    }

    /**
     * @return array<int, FormActionCondition>
     */
    public function getFormActionConditionsByFormId(int $formId): array
    {
        return $this->repository->findByFormId($formId);
    }

    /**
     * Save action conditions for a form.
     *
     * @param array<string, array<string, mixed>> $actionConditionsData
     */
    public function saveActionConditions(Form $form, array $actionConditionsData): void
    {
        $existingConditions = $this->getFormActionConditions($form);
        $processedActionIds = [];

        foreach ($actionConditionsData as $data) {
            $actionId = $data['actionId'] ?? null;

            if (!$actionId) {
                continue;
            }

            // Try to resolve the Action object. This handles both Integer IDs and 'new{HASH}' IDs.
            $action = $this->findActionById($form, $actionId);

            // If action passed in request does not exist on the form object, skip it.
            if (!$action) {
                continue;
            }

            // IMPORTANT: Now that we have the Action object, we get its REAL ID (database ID).
            // For 'new{HASH}' actions, the action object is found via the hash key,
            // but getId() returns the newly persisted integer because the form was just saved.
            $realActionId = $action->getId();

            if (!$realActionId) {
                continue;
            }

            $processedActionIds[] = $realActionId;
            $conditions           = $data['conditions'] ?? null;

            if ($conditions) {
                // Create or update condition. key logic by realActionId
                $condition = $existingConditions[$realActionId] ?? new FormActionCondition();
                $condition->setAction($action);
                $condition->setConditions($conditions);

                $this->entityManager->persist($condition);
            } elseif (isset($existingConditions[$realActionId])) {
                // Remove condition if conditions are empty
                $this->entityManager->remove($existingConditions[$realActionId]);
            }
        }

        // Remove conditions for actions that no longer exist or have no conditions
        foreach ($existingConditions as $actionId => $condition) {
            if (!in_array($actionId, $processedActionIds, true)) {
                $this->entityManager->remove($condition);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Finds an action by ID or by the temporary 'new{HASH}' key used in the collection.
     */
    private function findActionById(Form $form, int|string $actionId): ?Action
    {
        $actions = $form->getActions();

        // Direct lookup using the collection key.
        // FormModel keys the collection with the provided ID ('123' or 'new{hash}')
        if ($actions->containsKey($actionId)) {
            return $actions->get($actionId);
        }

        // Fallback: Iterate and match numeric ID.
        foreach ($actions as $action) {
            if ($action->getId() == $actionId) {
                return $action;
            }
        }

        return null;
    }
}
