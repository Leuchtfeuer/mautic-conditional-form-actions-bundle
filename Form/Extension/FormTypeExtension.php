<?php

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Form\Extension;

use Mautic\FormBundle\Form\Type\FormType;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Form\Type\FormActionConditionsConfigType;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Model\ActionConditionManager;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormView;
use Twig\Environment;

class FormTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private Config $pluginConfig,
        private ActionConditionManager $actionConditionManager,
        private Environment $twig
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$this->pluginConfig->isPublished()) {
            return;
        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, [$this, 'onPreSetData']);
    }

    public function onPreSetData(FormEvent $event): void
    {
        $form   = $event->getForm();
        $entity = $event->getData();

        $actionConditionsData = [];

        if ($entity->getId()) {
            // Existing form - load conditions for all actions
            $actionConditions = $this->actionConditionManager->getFormActionConditions($entity);

            foreach ($entity->getActions() as $action) {
                $actionId  = $action->getId();
                $condition = $actionConditions[$actionId] ?? null;

                $actionConditionsData[$actionId] = [
                    'actionId'   => $actionId,
                    'conditions' => $condition?->getConditions() ?? [],
                ];
            }
        }

        $form->add('actionConditionsConfig', FormActionConditionsConfigType::class, [
            'data' => [
                'actionConditions' => $actionConditionsData,
            ],
            'mapped'      => false,
            'mautic_form' => $entity,
            'label'       => false,
        ]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        if (!$this->pluginConfig->isPublished()) {
            return;
        }

        if (isset($view['actionConditionsConfig'])) {
            $this->twig->getRuntime(FormRenderer::class)
                ->setTheme(
                    $view['actionConditionsConfig'],
                    '@LeuchtfeuerConditionalFormActions/FormTheme/conditional_action_conditions.html.twig'
                );
        }
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
