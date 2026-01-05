<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Exception\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Helper\MigrationHelper;

class Version_0_0_2 extends AbstractMigration
{
    private string $table = 'form_actions_conditions';
    private Schema $schema;

    protected function isApplicable(Schema $schema): bool
    {
        $this->schema = $schema;

        try {
            return !$schema->hasTable($this->concatPrefix($this->table));
        } catch (SchemaException) {
            return false;
        }
    }

    protected function up(): void
    {
        $actionsTable        = $this->schema->getTable($this->concatPrefix('form_actions'));
        $actionsIdColumnType = MigrationHelper::getReferencedColumnType($actionsTable);

        $this->addSql("CREATE TABLE `{$this->concatPrefix($this->table)}`
(
    action_id      {$actionsIdColumnType}       NOT NULL,
    conditions LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)',
    PRIMARY KEY (action_id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB
  ROW_FORMAT = DYNAMIC;");

        $this->addSql("ALTER TABLE `{$this->concatPrefix($this->table)}` ADD CONSTRAINT FK_D8580F179D32F035 FOREIGN KEY (action_id) REFERENCES {$this->concatPrefix('form_actions')} (id) ON DELETE CASCADE;");
    }
}
