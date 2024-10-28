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
final class Version20241028112138 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ref BEAA2-10: create mein berlin addon table';
    }

    public function up(Schema $schema): void
    {
        $this->abortIfNotMysql();
        $this->addSql('DROP TABLE IF EXISTS mein_berlin_addon');
        $this->addSql('CREATE TABLE mein_berlin_addon (id CHAR(36) NOT NULL, procedure_id VARCHAR(36) NOT NULL, organisation_id VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema): void
    {
        $this->abortIfNotMysql();

        $this->addSql('DROP TABLE mein_berlin_addon');
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
