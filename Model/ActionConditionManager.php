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
        private FormActionConditionRepository $repository
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

            // Skip temporary IDs - we'll handle those separately
            if (!$actionId || str_starts_with((string) $actionId, 'temp_')) {
                continue;
            }

            $action = $this->findActionById($form, (int) $actionId);
            if (!$action) {
                continue;
            }

            $processedActionIds[] = (int) $actionId;
            $conditions           = $data['conditions'] ?? null;

            if ($conditions) {
                // Create or update condition
                $condition = $existingConditions[(int) $actionId] ?? new FormActionCondition();
                $condition->setAction($action);
                $condition->setConditions($conditions);

                $this->entityManager->persist($condition);
            } elseif (isset($existingConditions[(int) $actionId])) {
                // Remove condition if conditions are empty
                $this->entityManager->remove($existingConditions[(int) $actionId]);
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

    private function findActionById(Form $form, int $actionId): ?Action
    {
        foreach ($form->getActions() as $action) {
            if ($action->getId() === $actionId) {
                return $action;
            }
        }

        return null;
    }
}
