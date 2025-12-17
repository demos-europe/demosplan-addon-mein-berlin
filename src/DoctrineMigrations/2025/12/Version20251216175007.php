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
 * Manual district override for molkenmarkt procedure (Bebauungsplan 1-14-1)
 */
final class Version20251216175007 extends AbstractMigration
{
    // District code
    private const DISTRICT_MITTE = 'mi';

    // Procedure identifier
    private const PROCEDURE_MOLKENMARKT_SLUG = 'molkenmarkt';

    public function getDescription(): string
    {
        return 'Override district for molkenmarkt procedure (Bebauungsplan 1-14-1) to Mitte';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Check if tables exist
        if (!$schema->hasTable('addon_mein_berlin_entity') || !$schema->hasTable('_procedure') || !$schema->hasTable('slug')) {
            $this->write('Required tables do not exist, skipping migration');
            return;
        }

        // Update molkenmarkt procedure (Bebauungsplan 1-14-1) → Mitte
        $this->addSql(
            'UPDATE addon_mein_berlin_entity e
            JOIN _procedure p ON e.procedure_id = p._p_id
            JOIN slug s ON p.current_slug_id = s.id
            SET e.district = :district
            WHERE s.name = :slug',
            [
                'district' => self::DISTRICT_MITTE,
                'slug' => self::PROCEDURE_MOLKENMARKT_SLUG,
            ]
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Check if tables exist
        if (!$schema->hasTable('addon_mein_berlin_entity') || !$schema->hasTable('_procedure') || !$schema->hasTable('slug')) {
            $this->write('Required tables do not exist, skipping rollback');
            return;
        }

        // Revert to organisation-based mapping
        $this->addSql(
            'UPDATE addon_mein_berlin_entity e
            JOIN _procedure p ON e.procedure_id = p._p_id
            JOIN slug s ON p.current_slug_id = s.id
            SET e.district = \'\'
            WHERE s.name = :slug',
            ['slug' => self::PROCEDURE_MOLKENMARKT_SLUG]
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
