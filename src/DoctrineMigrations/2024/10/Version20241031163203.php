<?php

declare(strict_types=1);

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
        $this->addSql('CREATE TABLE IF NOT EXISTS mein_berlin_addon_orga_relation (id CHAR(36) NOT NULL, orga_id CHAR(36) NOT NULL, mein_berlin_organisation_id VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('DROP TABLE IF EXISTS mein_berlin_addon_orga_relation');
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
