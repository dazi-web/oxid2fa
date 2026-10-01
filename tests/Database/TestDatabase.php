<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Database;

use DaziWeb\Oxid2Fa\Migrations\Version20261001090000;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use OxidEsales\EshopCommunity\Internal\Framework\Database\ConnectionProviderInterface;
use Psr\Log\NullLogger;

/**
 * A plain database connection for the tests, configured by environment variables, with the schema created by
 * the module's real migration. No shop is needed.
 */
final class TestDatabase
{
    public static function connection(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => getenv('OXID2FA_TEST_DB_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('OXID2FA_TEST_DB_PORT') ?: 3306),
            'user' => getenv('OXID2FA_TEST_DB_USER') ?: 'root',
            'password' => getenv('OXID2FA_TEST_DB_PASSWORD') ?: '',
            'dbname' => getenv('OXID2FA_TEST_DB_NAME') ?: 'oxid2fa_test',
            'charset' => 'utf8',
        ]);
    }

    public static function provider(Connection $connection): ConnectionProviderInterface
    {
        return new class ($connection) implements ConnectionProviderInterface {
            public function __construct(private readonly Connection $connection)
            {
            }

            public function get(): Connection
            {
                return $this->connection;
            }
        };
    }

    /** Drops the module tables and creates them again with the real migration. */
    public static function migrate(Connection $connection): void
    {
        self::dropTables($connection);

        $migration = new Version20261001090000($connection, new NullLogger());
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    public static function dropTables(Connection $connection): void
    {
        // the recovery codes first: they point at the enrolments
        $tables = [
            'oxid2fa_twofactor_recovery_code',
            'oxid2fa_twofactor',
            'oxid2fa_twofactor_attempt',
            'oxid2fa_required',
        ];
        foreach ($tables as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS ' . $table);
        }
    }
}
