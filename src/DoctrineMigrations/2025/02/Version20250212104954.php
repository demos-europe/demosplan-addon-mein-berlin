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

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\AbortMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250212104954 extends AbstractMigration
{
    private const PICTOGRAM_PERMISSION = 'feature_procedure_pictogram_resolution_restriction';
    private const SUBDOMAIN = 'be';
    public function getDescription(): string
    {
        return 'add access control permissions (pictogram file size and min resolution restrictions)
         for already established orgaRelations';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        // fetch and assert customerId to set the permission for
        $customerId = $this->connection->fetchOne(
            'SELECT _c_id FROM customer WHERE _c_subdomain = :subdomain',
            ['subdomain' => self::SUBDOMAIN]
        );
        if (false === $customerId) {
            throw new AbortMigration('no customer found for subdomain '.self::SUBDOMAIN);
        }
        // fetch and assert the roleIds to set the permissions for
        $roleIds = $this->connection->fetchAllAssociative(
            'SELECT _r_id as roleId FROM _role WHERE _r_code = :RMOPSA OR _r_code = :RMOPSD',
            ['RMOPSA' => RoleInterface::PLANNING_AGENCY_ADMIN, 'RMOPSD' => RoleInterface::PLANNING_AGENCY_WORKER]
        );
        $roleIds = array_map(
            static fn ($roleId) => $roleId['roleId'],
            $roleIds
        );
        if (count($roleIds) !== 2) {
            throw new AbortMigration('failed to query necessary roleIds');
        }
        // get all orga-ids where a mein-berlin relation has already been set
        $orgaIds = $this->connection->fetchAllAssociative(
            'SELECT orga_id as orgaId FROM addon_mein_berlin_orga_relation'
        );
        // check if access control permission already exists to not set them again
        $correctlySetOrgaIds = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT orga_id as orgaId From access_control ac
                    WHERE ac.permission = :pictogramPermission',
            ['pictogramPermission' => self::PICTOGRAM_PERMISSION]
        );
        $correctlySetOrgaIds = array_map(
            static fn ($queriedAccessControlParams) => $queriedAccessControlParams['orgaId'],
            $correctlySetOrgaIds
        );
        // set missing access control permissions
        foreach ($orgaIds as $orgaToCheck) {
            $orgaId = $orgaToCheck['orgaId'] ?? null;
            if ($orgaId !== null && !in_array($orgaId, $correctlySetOrgaIds)) {
                foreach ($roleIds as $roleId) {
                    $this->addSql(
                        'INSERT INTO access_control(id, orga_id, role_id, customer_id, permission, creation_date, modification_date)
                    VALUES (
                            UUID(),
                            :orgaId,
                            :roleId,
                            :customerId,
                            :permission,
                            NOW(),
                            NOW()
                    )',
                        [
                            'orgaId' => $orgaId,
                            'roleId' => $roleId,
                            'customerId' => $customerId,
                            'permission' => self::PICTOGRAM_PERMISSION,
                        ]
                    );
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        // this can not be restored as it is unknown if the permission was set for some organisations correctly before
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
