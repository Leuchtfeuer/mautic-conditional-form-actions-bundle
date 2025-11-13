<?php

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * @extends CommonRepository<FormActionCondition>
 */
class FormActionConditionRepository extends CommonRepository
{
    /**
     * @param int $formId
     * @return array<int, FormActionCondition>
     */
    public function findByFormId(int $formId): array
    {
        $qb = $this->createQueryBuilder('fac');
        $qb->join('fac.action', 'a')
            ->where('a.form = :formId')
            ->setParameter('formId', $formId);

        $results = $qb->getQuery()->getResult();

        // Index by action ID for easy lookup
        $indexed = [];
        foreach ($results as $condition) {
            $indexed[$condition->getAction()->getId()] = $condition;
        }

        return $indexed;
    }

    public function findByActionId(int $actionId): ?FormActionCondition
    {
        return $this->findOneBy(['action' => $actionId]);
    }
}
