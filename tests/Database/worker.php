<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

/**
 * One database operation in its own PHP process, started at a given moment so that several workers hit the
 * database at the same time. Prints the result. Usage: worker.php <startAt> <operation> <argument>...
 */

use DaziWeb\Oxid2Fa\Infrastructure\DbChallengeThrottle;
use DaziWeb\Oxid2Fa\Infrastructure\DbEnrollmentRepository;
use DaziWeb\Oxid2Fa\Infrastructure\DbRecoveryCodeRepository;
use DaziWeb\Oxid2Fa\Tests\Database\TestDatabase;
use Symfony\Component\Clock\NativeClock;

require __DIR__ . '/../../vendor/autoload.php';

[, $startAt, $operation] = $argv + [null, '0', ''];
$arguments = array_slice($argv, 3);

$provider = TestDatabase::provider(TestDatabase::connection());
$clock = new NativeClock();

// connect first, then wait: the race should be about the statement, not about the process start
$provider->get()->executeQuery('SELECT 1');
while (microtime(true) < (float)$startAt) {
    usleep(200);
}

echo match ($operation) {
    'attempt' => (new DbChallengeThrottle($provider, $clock))->reserveAttempt($arguments[0]),
    'consume' => (new DbRecoveryCodeRepository($provider))->consume($arguments[0], $arguments[1], $clock->now())
        ? 1
        : 0,
    'claim' => (new DbEnrollmentRepository($provider))->claimStep($arguments[0], (int)$arguments[1]) ? 1 : 0,
    'activate' => (new DbEnrollmentRepository($provider))->activate($arguments[0], $clock->now()) ? 1 : 0,
    default => throw new InvalidArgumentException('Unknown operation ' . $operation),
};
