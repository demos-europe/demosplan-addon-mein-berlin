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
 * Remove field_procedure_pictogram permission entries from access control.
 */
final class Version20251126140557 extends AbstractMigration
{
    private const PICTOGRAM_PERMISSION = 'field_procedure_pictogram';
    private const BERLIN_SUBDOMAIN = 'be';
    private const ROLE_PLANNING_AGENCY_ADMIN = 'RMOPSA';
    private const ROLE_PLANNING_AGENCY_WORKER = 'RMOPSD';

    public function getDescription(): string
    {
        return 'Remove field_procedure_pictogram permission entries from access control table';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Remove field_procedure_pictogram permission entries for all organisations that have a mein.berlin organisation ID
        // This ensures we only delete permissions created by the mein.berlin addon, regardless of customer
        $this->addSql(
            'DELETE ac FROM access_control ac
             INNER JOIN addon_mein_berlin_orga_relation rel
                 ON ac.orga_id = rel.orga_id
             WHERE ac.permission = :permission',
            ['permission' => self::PICTOGRAM_PERMISSION]
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // Restore field_procedure_pictogram permissions for all organisations that have a mein.berlin organisation ID
        // Get Berlin customer via subdomain 'be' (this addon is only used in Berlin on production)
        // For both PLANNING_AGENCY_ADMIN (RMOPSA) and PLANNING_AGENCY_WORKER (RMOPSD) roles
        $this->addSql(
            'INSERT INTO access_control (id, orga_id, customer_id, role_id, permission, creation_date, modification_date)
             SELECT
                 UUID(),
                 rel.orga_id,
                 c._c_id,
                 r._r_id,
                 :permission,
                 NOW(),
                 NOW()
             FROM addon_mein_berlin_orga_relation rel
             CROSS JOIN customer c
             CROSS JOIN _role r
             WHERE c._c_subdomain = :subdomain
               AND (r._r_code = :roleAdmin OR r._r_code = :roleWorker)
               AND NOT EXISTS (
                 SELECT 1 FROM access_control ac
                 WHERE ac.orga_id = rel.orga_id
                   AND ac.customer_id = c._c_id
                   AND ac.role_id = r._r_id
                   AND ac.permission = :permission
             )',
            [
                'permission' => self::PICTOGRAM_PERMISSION,
                'subdomain' => self::BERLIN_SUBDOMAIN,
                'roleAdmin' => self::ROLE_PLANNING_AGENCY_ADMIN,
                'roleWorker' => self::ROLE_PLANNING_AGENCY_WORKER,
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
