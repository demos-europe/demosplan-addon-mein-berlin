<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Application\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241031163203 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ref BEAA2-10: create mein berlin addon orga relation table';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('CREATE TABLE IF NOT EXISTS addon_mein_berlin_orga_relation (id CHAR(36) NOT NULL, orga_id CHAR(36) NOT NULL, mein_berlin_organisation_id VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_42D619E2E3B889DC (orga_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE addon_mein_berlin_orga_relation ADD CONSTRAINT FK_42D619E2E3B889DC FOREIGN KEY (orga_id) REFERENCES _orga (_o_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        // check if table exists
        $tableExists = $this->connection->fetchOne("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_NAME = 'addon_mein_berlin_orga_relation'
            "
        );
        if (0 !== $tableExists || false !== $tableExists) {
            // if it does - drop the foreign key(s) first
            $this->addSql('ALTER TABLE addon_mein_berlin_orga_relation DROP FOREIGN KEY FK_42D619E2E3B889DC');
            $this->addSql('ALTER TABLE addon_mein_berlin_orga_relation DROP INDEX UNIQ_42D619E2E3B889DC');
        }

        $this->addSql('DROP TABLE IF EXISTS addon_mein_berlin_orga_relation');
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
