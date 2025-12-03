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
 * Manual district override for 3 production procedures that don't follow the organisation mapping
 */
final class Version20251201131200 extends AbstractMigration
{
    // District codes
    private const DISTRICT_FRIEDRICHSHAIN_KREUZBERG = 'fk';
    private const DISTRICT_MITTE = 'mi';

    // Procedure identifiers
    private const PROCEDURE_CAMPUS_SLUG = 'campus-fuer-entwicklungszusammenarbeit';
    private const PROCEDURE_BERLINER_MAUER_ID = 'aecdf788-4495-4632-ae85-2d1392339e3a';
    private const PROCEDURE_ROBERT_KOCH_ID = 'd47153ef-0000-4b98-a9e3-42d5f3abf69c';

    public function getDescription(): string
    {
        return 'Override district for 3 specific production procedures that require manual mapping';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Check if tables exist
        if (!$schema->hasTable('addon_mein_berlin_entity') || !$schema->hasTable('_procedure') || !$schema->hasTable('slug')) {
            $this->write('Required tables do not exist, skipping migration');
            return;
        }

        // Update specific procedures by their slug or UUID
        // Procedure 1: Campus für Entwicklungszusammenarbeit → Friedrichshain-Kreuzberg
        $this->addSql(
            'UPDATE addon_mein_berlin_entity e
            JOIN _procedure p ON e.procedure_id = p._p_id
            JOIN slug s ON p.current_slug_id = s.id
            SET e.district = :district
            WHERE s.name = :slug',
            [
                'district' => self::DISTRICT_FRIEDRICHSHAIN_KREUZBERG,
                'slug' => self::PROCEDURE_CAMPUS_SLUG,
            ]
        );

        // Procedure 2: Gedenkstätte Berliner Mauer → Mitte
        $this->addSql(
            'UPDATE addon_mein_berlin_entity e
            JOIN _procedure p ON e.procedure_id = p._p_id
            SET e.district = :district
            WHERE p._p_id = :procedureId',
            [
                'district' => self::DISTRICT_MITTE,
                'procedureId' => self::PROCEDURE_BERLINER_MAUER_ID,
            ]
        );

        // Procedure 3: Robert Koch-Institut → Mitte
        $this->addSql(
            'UPDATE addon_mein_berlin_entity e
            JOIN _procedure p ON e.procedure_id = p._p_id
            SET e.district = :district
            WHERE p._p_id = :procedureId',
            [
                'district' => self::DISTRICT_MITTE,
                'procedureId' => self::PROCEDURE_ROBERT_KOCH_ID,
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

        // Revert to organisation-based mapping for these procedures
        // All three have OrgaID 14, which would normally map to nothing in our CASE statement
        // So we reset them to empty string
        $this->addSql(
            'UPDATE addon_mein_berlin_entity e
            JOIN _procedure p ON e.procedure_id = p._p_id
            JOIN slug s ON p.current_slug_id = s.id
            SET e.district = \'\'
            WHERE s.name = :slug',
            ['slug' => self::PROCEDURE_CAMPUS_SLUG]
        );

        $this->addSql(
            'UPDATE addon_mein_berlin_entity e
            JOIN _procedure p ON e.procedure_id = p._p_id
            SET e.district = \'\'
            WHERE p._p_id IN (:procedure1, :procedure2)',
            [
                'procedure1' => self::PROCEDURE_BERLINER_MAUER_ID,
                'procedure2' => self::PROCEDURE_ROBERT_KOCH_ID,
            ]
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
