<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Exception\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;
use MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Helper\MigrationHelper;

class Version_0_0_3 extends AbstractMigration
{
    private string $table = 'form_action_execution_logs';
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
        $actionsTable     = $this->schema->getTable($this->concatPrefix('form_actions'));
        $submissionsTable = $this->schema->getTable($this->concatPrefix('form_submissions'));

        $actionIdType     = MigrationHelper::getReferencedColumnType($actionsTable);
        $submissionIdType = MigrationHelper::getReferencedColumnType($submissionsTable);

        $this->addSql("CREATE TABLE `{$this->concatPrefix($this->table)}`
(
    id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    submission_id {$submissionIdType} NOT NULL,
    action_id {$actionIdType} NOT NULL,
    is_executed TINYINT(1) NOT NULL,
    log_details LONGTEXT DEFAULT NULL,
    date_added DATETIME NOT NULL,
    INDEX IDX_2C6D16EDE1FD4933 (submission_id),
    INDEX IDX_2C6D16ED9D32F035 (action_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB ROW_FORMAT = DYNAMIC;");

        $this->addSql("ALTER TABLE `{$this->concatPrefix($this->table)}` ADD CONSTRAINT FK_2C6D16EDE1FD4933 FOREIGN KEY (submission_id) REFERENCES {$this->concatPrefix('form_submissions')} (id) ON DELETE CASCADE;");
        $this->addSql("ALTER TABLE `{$this->concatPrefix($this->table)}` ADD CONSTRAINT FK_2C6D16ED9D32F035 FOREIGN KEY (action_id) REFERENCES {$this->concatPrefix('form_actions')} (id) ON DELETE CASCADE;");
    }
}
