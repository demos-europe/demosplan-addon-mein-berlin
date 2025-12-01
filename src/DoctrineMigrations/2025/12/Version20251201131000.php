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
 * Populate district field based on mein_berlin_organisation_id
 */
final class Version20251201131000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Populate district field with district codes based on mein_berlin_organisation_id';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Check if tables exist
        if (!$schema->hasTable('addon_mein_berlin_entity') || !$schema->hasTable('addon_mein_berlin_orga_relation')) {
            $this->write('Required tables do not exist, skipping migration');
            return;
        }

        // Populate district field based on mein_berlin_organisation_id mapping
        $this->addSql("
            UPDATE addon_mein_berlin_entity e
            JOIN _procedure p ON e.procedure_id = p._p_id
            JOIN addon_mein_berlin_orga_relation o ON p._o_id = o.orga_id
            SET e.district = CASE o.mein_berlin_organisation_id
                WHEN '16' THEN 'mi'
                WHEN '27' THEN 'cw'
                WHEN '28' THEN 'fk'
                WHEN '20' THEN 'pa'
                WHEN '26' THEN 'sp'
                WHEN '32' THEN 'sz'
                WHEN '24' THEN 'ts'
                WHEN '15' THEN 'tk'
                WHEN '25' THEN 'mh'
                WHEN '29' THEN 'li'
                WHEN '31' THEN 'rd'
                ELSE ''
            END
            WHERE e.district = '' OR e.district IS NULL
        ");
    }

    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Check if table exists
        if (!$schema->hasTable('addon_mein_berlin_entity')) {
            $this->write('Table addon_mein_berlin_entity does not exist, skipping rollback');
            return;
        }

        // Clear district field
        $this->addSql("UPDATE addon_mein_berlin_entity SET district = ''");
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
