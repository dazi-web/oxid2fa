<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Database;

use DaziWeb\Oxid2Fa\Migrations\Version20261001090000;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;

#[CoversClass(Version20261001090000::class)]
final class MigrationTest extends DatabaseTestCase
{
    private const TABLES = [
        'oxid2fa_twofactor',
        'oxid2fa_twofactor_recovery_code',
        'oxid2fa_twofactor_attempt',
        'oxid2fa_required',
    ];

    public function testTheMigrationCreatesAllTablesAndCanBeRolledBack(): void
    {
        $this->assertSame(self::TABLES, $this->existingTables(), 'up()');

        $migration = new Version20261001090000(self::$connection, new NullLogger());
        $migration->down(new Schema());
        foreach ($migration->getSql() as $query) {
            self::$connection->executeStatement($query->getStatement());
        }
        $this->assertSame([], $this->existingTables(), 'down()');

        TestDatabase::migrate(self::$connection); // leave the schema for the next test class
    }

    /** @return list<string> */
    private function existingTables(): array
    {
        $existing = array_map('strval', self::$connection->fetchFirstColumn('SHOW TABLES'));

        return array_values(array_intersect(self::TABLES, $existing));
    }
}
