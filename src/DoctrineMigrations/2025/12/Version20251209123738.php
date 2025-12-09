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
 * Manual district override for alte-gaertnerei procedure
 */
final class Version20251209123738 extends AbstractMigration
{
    // District code
    private const DISTRICT_PANKOW = 'pa';

    // Procedure identifier
    private const PROCEDURE_ALTE_GAERTNEREI_SLUG = 'alte-gaertnerei';

    public function getDescription(): string
    {
        return 'Override district for alte-gaertnerei procedure to Pankow';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Check if tables exist
        if (!$schema->hasTable('addon_mein_berlin_entity') || !$schema->hasTable('_procedure') || !$schema->hasTable('slug')) {
            $this->write('Required tables do not exist, skipping migration');
            return;
        }

        // Update alte-gaertnerei procedure → Pankow
        $this->addSql(
            'UPDATE addon_mein_berlin_entity e
            JOIN _procedure p ON e.procedure_id = p._p_id
            JOIN slug s ON p.current_slug_id = s.id
            SET e.district = :district
            WHERE s.name = :slug',
            [
                'district' => self::DISTRICT_PANKOW,
                'slug' => self::PROCEDURE_ALTE_GAERTNEREI_SLUG,
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
            ['slug' => self::PROCEDURE_ALTE_GAERTNEREI_SLUG]
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
