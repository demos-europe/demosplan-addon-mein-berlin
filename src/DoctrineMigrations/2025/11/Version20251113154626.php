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
 * Add is_interface_activated field to addon_mein_berlin_entity table
 * and migrate existing procedures that have been transmitted to activated status
 */
final class Version20251113154626 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_interface_activated field to addon_mein_berlin_entity table and migrate existing transmitted procedures to activated status';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Add the new column with default value false
        $this->addSql(
            'ALTER TABLE addon_mein_berlin_entity
             ADD COLUMN is_interface_activated TINYINT(1) NOT NULL DEFAULT 0
             COMMENT \'Whether the mein.berlin.de interface is activated for this procedure\''
        );

        // Set is_interface_activated to true for all procedures that have already been transmitted
        // (i.e., have a non-empty dplan_id)
        $this->addSql(
            'UPDATE addon_mein_berlin_entity
             SET is_interface_activated = 1
             WHERE dplan_id != \'\' AND dplan_id IS NOT NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Remove the column
        $this->addSql('ALTER TABLE addon_mein_berlin_entity DROP COLUMN is_interface_activated');
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
