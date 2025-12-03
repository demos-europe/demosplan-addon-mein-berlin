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
 * Rename dplan_id column to bplan_id for consistency with mein.berlin API
 */
final class Version20251201131100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename dplan_id column to bplan_id for consistency with mein.berlin API naming';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Check if table exists
        if (!$schema->hasTable('addon_mein_berlin_entity')) {
            $this->write('Table addon_mein_berlin_entity does not exist, skipping migration');
            return;
        }

        $table = $schema->getTable('addon_mein_berlin_entity');

        // Check if old column exists and new column doesn't exist yet
        if (!$table->hasColumn('dplan_id')) {
            $this->write('Column dplan_id does not exist, skipping migration');
            return;
        }

        if ($table->hasColumn('bplan_id')) {
            $this->write('Column bplan_id already exists, skipping migration');
            return;
        }

        // Rename column
        $this->addSql('ALTER TABLE addon_mein_berlin_entity CHANGE dplan_id bplan_id VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Check if table exists
        if (!$schema->hasTable('addon_mein_berlin_entity')) {
            $this->write('Table addon_mein_berlin_entity does not exist, skipping rollback');
            return;
        }

        $table = $schema->getTable('addon_mein_berlin_entity');

        // Check if new column exists and old column doesn't exist yet
        if (!$table->hasColumn('bplan_id')) {
            $this->write('Column bplan_id does not exist, skipping rollback');
            return;
        }

        if ($table->hasColumn('dplan_id')) {
            $this->write('Column dplan_id already exists, skipping rollback');
            return;
        }

        // Rename back
        $this->addSql('ALTER TABLE addon_mein_berlin_entity CHANGE bplan_id dplan_id VARCHAR(255) NOT NULL');
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
