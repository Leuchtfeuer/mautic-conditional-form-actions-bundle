<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Integration\Support;

use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Integration\LeuchtfeuerConditionalFormActionsIntegration;

class ConfigSupport extends LeuchtfeuerConditionalFormActionsIntegration implements ConfigFormInterface
{
    use DefaultConfigFormTrait;
}
