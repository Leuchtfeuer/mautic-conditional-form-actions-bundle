<?php

declare(strict_types=1);

return [
    'name'        => 'ConditionalFormActions by Leuchtfeuer',
    'description' => 'Enables conditional form submit actions',
    'version'     => '7.0.0',
    'author'      => 'Leuchtfeuer Digital Marketing GmbH',
    'routes'      => [
        'main' => [
            'mautic_cfa_render_condition_action' => [
                'path'       => '/forms-cfa/render-condition',
                'controller' => 'MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Controller\ConditionBuilderController::renderConditionAction',
            ],
        ],
    ],
];
