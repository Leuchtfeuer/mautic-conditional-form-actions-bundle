<?php

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\FormBundle\Entity\Action;
use Mautic\FormBundle\Entity\Submission;

/**
 * @extends CommonRepository<FormActionExecutionLog>
 */
class FormActionExecutionLogRepository extends CommonRepository
{
    public function logExecution(
        Submission $submission,
        Action $action,
        bool $isExecuted,
        ?string $details = null,
    ): void {
        $log = new FormActionExecutionLog();
        $log->setSubmission($submission);
        $log->setAction($action);
        $log->setIsExecuted($isExecuted);
        $log->setLogDetails($details);
        $this->getEntityManager()->persist($log);
        $this->getEntityManager()->flush();
    }
}
