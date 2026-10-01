<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Database;

use Doctrine\DBAL\Connection;
use OxidEsales\EshopCommunity\Internal\Framework\Database\ConnectionProviderInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests against a real database. Without one they are skipped locally; with OXID2FA_REQUIRE_DB=1 (as in the CI)
 * a missing database is a failure, so the SQL can never silently go untested.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected static Connection $connection;
    protected ConnectionProviderInterface $provider;

    public static function setUpBeforeClass(): void
    {
        try {
            self::$connection = TestDatabase::connection();
            self::$connection->executeQuery('SELECT 1');
        } catch (\Throwable $unavailable) {
            if (getenv('OXID2FA_REQUIRE_DB') === '1') {
                self::fail('Test database is required but not reachable: ' . $unavailable->getMessage());
            }
            self::markTestSkipped('No test database (set OXID2FA_TEST_DB_* to run these tests).');
        }

        TestDatabase::migrate(self::$connection);
    }

    protected function setUp(): void
    {
        $this->provider = TestDatabase::provider(self::$connection);
        foreach (['oxid2fa_twofactor', 'oxid2fa_twofactor_attempt', 'oxid2fa_required'] as $table) {
            self::$connection->executeStatement('DELETE FROM ' . $table);
        }
    }
}
