<?php

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\FormBundle\Entity\Action;

#[ORM\Entity(repositoryClass: FormActionConditionRepository::class)]
#[ORM\Table(name: 'form_actions_conditions')]
class FormActionCondition
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: Action::class)]
    #[ORM\JoinColumn(name: 'action_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Action $action;

    /**
     * @var array<mixed>|null
     */
    #[ORM\Column(name: 'conditions', type: 'json', nullable: true)]
    private ?array $conditions = null;

    public function getAction(): Action
    {
        return $this->action;
    }

    public function setAction(Action $action): self
    {
        $this->action = $action;

        return $this;
    }

    /**
     * @return array<mixed>|null
     */
    public function getConditions(): ?array
    {
        return $this->conditions;
    }

    /**
     * @param array<mixed>|null $conditions
     */
    public function setConditions(?array $conditions): self
    {
        $this->conditions = $conditions;

        return $this;
    }
}
