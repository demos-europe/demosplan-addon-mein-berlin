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
 * Migrate existing transmitted procedures to activated status
 */
final class Version20251125152256 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set is_interface_activated to true for procedures that have already been transmitted to mein.berlin.de';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

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

        // Reset is_interface_activated to false for previously transmitted procedures
        $this->addSql(
            'UPDATE addon_mein_berlin_entity
             SET is_interface_activated = 0
             WHERE dplan_id != \'\' AND dplan_id IS NOT NULL'
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
