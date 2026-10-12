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

final class Version20261006131519 extends AbstractMigration
{
    /**
     * Initial content of the district table: name and organisation id on mein.berlin.de by district code.
     * The codes are the ones stored at procedures. Afterwards the table is the only source, the organisation ids
     * are the ones that were hard coded in the frontend until now. "be" (gesamtstaedtisch) has none yet.
     */
    private const DISTRICTS = [
        'be' => ['name' => 'Gesamtstädtisch', 'organisationId' => null],
        'mi' => ['name' => 'Mitte', 'organisationId' => '16'],
        'fk' => ['name' => 'Friedrichshain-Kreuzberg', 'organisationId' => '28'],
        'pa' => ['name' => 'Pankow', 'organisationId' => '20'],
        'cw' => ['name' => 'Charlottenburg-Wilmersdorf', 'organisationId' => '27'],
        'sp' => ['name' => 'Spandau', 'organisationId' => '26'],
        'sz' => ['name' => 'Steglitz-Zehlendorf', 'organisationId' => '32'],
        'ts' => ['name' => 'Tempelhof-Schöneberg', 'organisationId' => '24'],
        'nk' => ['name' => 'Neukölln', 'organisationId' => '30'],
        'tk' => ['name' => 'Treptow-Köpenick', 'organisationId' => '15'],
        'mh' => ['name' => 'Marzahn-Hellersdorf', 'organisationId' => '25'],
        'li' => ['name' => 'Lichtenberg', 'organisationId' => '29'],
        'rd' => ['name' => 'Reinickendorf', 'organisationId' => '31'],
    ];

    public function getDescription(): string
    {
        return 'refs BEAA2-44: create mein berlin addon district table and fill it with the known organisation ids';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('CREATE TABLE IF NOT EXISTS addon_mein_berlin_district (id CHAR(36) NOT NULL, district_code VARCHAR(2) NOT NULL, name VARCHAR(255) NOT NULL, mein_berlin_organisation_id VARCHAR(255) DEFAULT NULL, UNIQUE INDEX unique_district_code (district_code), UNIQUE INDEX unique_district_organisation_id (mein_berlin_organisation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');

        foreach (self::DISTRICTS as $districtCode => $district) {
            // INSERT IGNORE: the unique index on district_code keeps an already existing row untouched
            $this->addSql(
                'INSERT IGNORE INTO addon_mein_berlin_district (id, district_code, name, mein_berlin_organisation_id)
                 VALUES (UUID(), ?, ?, ?)',
                [$districtCode, $district['name'], $district['organisationId']]
            );
        }

        $this->reportIdsWithoutDistrict();
    }

    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('DROP TABLE IF EXISTS addon_mein_berlin_district');
    }

    /**
     * The organisation ids saved for organisations are not touched by this migration. Ids that belong to no
     * district of the table are reported, they stay available for the organisations that use them.
     *
     * @throws Exception
     */
    private function reportIdsWithoutDistrict(): void
    {
        $tableExists = 1 === (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'addon_mein_berlin_orga_relation'"
        );
        if (!$tableExists) {
            return;
        }

        $idsInUse = $this->connection->fetchFirstColumn(
            "SELECT DISTINCT mein_berlin_organisation_id FROM addon_mein_berlin_orga_relation
             WHERE mein_berlin_organisation_id <> ''"
        );
        foreach (array_diff($idsInUse, array_filter(array_column(self::DISTRICTS, 'organisationId'))) as $organisationId) {
            $this->write(sprintf(
                'Organisation id "%s" is saved for organisations but belongs to no known district, it stays unchanged',
                $organisationId
            ));
        }
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
