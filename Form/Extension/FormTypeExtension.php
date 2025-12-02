<?php

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Form\Extension;

use Mautic\FormBundle\Entity\Action;
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
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

class FormTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private Config $pluginConfig,
        private ActionConditionManager $actionConditionManager,
        private Environment $twig,
        private RequestStack $requestStack,
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
        if (!$this->pluginConfig->isPublished()) {
            return;
        }

        $symfonyForm   = $event->getForm();
        $mauticForm    = $event->getData();

        $actionConditionsData = [];

        if ($mauticForm->getId()) {
            // Existing form - load conditions for all actions
            $actionConditions = $this->actionConditionManager->getFormActionConditions($mauticForm);

            foreach ($mauticForm->getActions() as $action) {
                $actionId  = $action->getId();
                $condition = $actionConditions[$actionId] ?? null;

                $actionConditionsData[$actionId] = [
                    'actionId'   => $actionId,
                    'conditions' => $condition?->getConditions() ?? [],
                ];
            }
        } elseif ($this->isCloneRequest()) {
            $mainRequest  = $this->requestStack->getMainRequest();
            $sourceFormId = (int) $mainRequest?->attributes->get('objectId');

            if ($sourceFormId > 0) {
                $actionConditions = $this->actionConditionManager->getFormActionConditionsByFormId($sourceFormId);

                foreach ($mauticForm->getActions() as $index => $action) {
                    $tempId    = 'new'.hash('sha1', uniqid((string) mt_rand()));
                    $condition = $actionConditions[$index] ?? null;

                    $this->forceActionId($action, $tempId);
                    $actionConditionsData[$tempId] = [
                        'actionId'   => $tempId,
                        'conditions' => $condition?->getConditions() ?? [],
                    ];
                }
            }
        }

        $symfonyForm->add('actionConditionsConfig', FormActionConditionsConfigType::class, [
            'data' => [
                'actionConditions' => $actionConditionsData,
            ],
            'mapped'      => false,
            'mautic_form' => $mauticForm,
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

    private function isCloneRequest(): bool
    {
        $mainRequest = $this->requestStack->getMainRequest();

        return $mainRequest && 'clone' === $mainRequest->attributes->get('objectAction');
    }

    /**
     * Mautic Action Entities usually don't have a public setId().
     * We use Reflection to modify the private property on the clone instance.
     */
    private function forceActionId(Action $action, string $id): void
    {
        try {
            $reflection = new \ReflectionClass($action);
            $property   = $reflection->getProperty('id');
            $property->setValue($action, $id);
        } catch (\ReflectionException) {
        }
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
