<?php

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\FormBundle\Entity\Action;
use Mautic\FormBundle\Entity\Submission;

#[ORM\Entity(repositoryClass: FormActionExecutionLogRepository::class)]
#[ORM\Table(name: 'form_action_execution_logs')]
class FormActionExecutionLog
{

    public const DETAILS_CONDITIONS_MET = 'conditions_met';
    public const DETAILS_CONDITIONS_NOT_MET = 'conditions_not_met';
    public const DETAILS_ERROR = 'execution_error';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Submission::class)]
    #[ORM\JoinColumn(name: 'submission_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Submission $submission;

    #[ORM\ManyToOne(targetEntity: Action::class)]
    #[ORM\JoinColumn(name: 'action_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Action $action;

    #[ORM\Column(name: 'is_executed', type: 'boolean')]
    private bool $isExecuted;

    #[ORM\Column(name: 'log_details', type: 'text', nullable: true)]
    private ?string $logDetails = null;

    #[ORM\Column(name: 'date_added', type: 'datetime')]
    private \DateTime $dateAdded;

    public function __construct()
    {
        $this->dateAdded = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubmission(): Submission
    {
        return $this->submission;
    }

    public function setSubmission(Submission $submission): self
    {
        $this->submission = $submission;
        return $this;
    }

    public function getAction(): Action
    {
        return $this->action;
    }

    public function setAction(Action $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function isExecuted(): bool
    {
        return $this->isExecuted;
    }

    public function setIsExecuted(bool $isExecuted): self
    {
        $this->isExecuted = $isExecuted;
        return $this;
    }

    public function getLogDetails(): ?string
    {
        return $this->logDetails;
    }

    public function setLogDetails(?string $logDetails): self
    {
        $this->logDetails = $logDetails;
        return $this;
    }

    public function getDateAdded(): \DateTime
    {
        return $this->dateAdded;
    }
}