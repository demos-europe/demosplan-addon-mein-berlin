<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\DoctrineMigrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Replace procedure_short_name with district field
 */
final class Version20251201130900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace procedure_short_name field with district field (varchar 2) - schema change only';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Check if table exists before altering
        if (!$schema->hasTable('addon_mein_berlin_entity')) {
            $this->write('Table addon_mein_berlin_entity does not exist, skipping migration');
            return;
        }

        // Check if old column exists
        $table = $schema->getTable('addon_mein_berlin_entity');
        if (!$table->hasColumn('procedure_short_name')) {
            $this->write('Column procedure_short_name does not exist, skipping migration');
            return;
        }

        // Drop old column and add new district column
        $this->addSql('ALTER TABLE addon_mein_berlin_entity DROP COLUMN procedure_short_name');
        $this->addSql(
            'ALTER TABLE addon_mein_berlin_entity
             ADD COLUMN district VARCHAR(2) NOT NULL DEFAULT \'\' AFTER dplan_id'
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Check if table exists before reverting
        if (!$schema->hasTable('addon_mein_berlin_entity')) {
            $this->write('Table addon_mein_berlin_entity does not exist, skipping rollback');
            return;
        }

        // Check if new column exists
        $table = $schema->getTable('addon_mein_berlin_entity');
        if (!$table->hasColumn('district')) {
            $this->write('Column district does not exist, skipping rollback');
            return;
        }

        // Drop new column and restore old column
        $this->addSql('ALTER TABLE addon_mein_berlin_entity DROP COLUMN district');
        $this->addSql(
            'ALTER TABLE addon_mein_berlin_entity
             ADD COLUMN procedure_short_name VARCHAR(255) NOT NULL DEFAULT \'\' AFTER dplan_id'
        );
    }

    /**
     * @throws Exception
     */
    private function abortIfNotMysql(): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform,
            "Migration can only be executed safely on 'mysql'."
        );
    }
}
