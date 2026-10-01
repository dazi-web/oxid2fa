<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Infrastructure;

use OxidEsales\EshopCommunity\Internal\Framework\Database\ConnectionProviderInterface;
use DaziWeb\Oxid2Fa\Application\ChallengeThrottle;
use Psr\Clock\ClockInterface;

/**
 * Counts attempts per account, not per IP: the account is what is being attacked, and an IP is trivial
 * to change. Attempts older than the lock duration are forgotten.
 */
final readonly class DbChallengeThrottle implements ChallengeThrottle
{
    private const TABLE = 'oxid2fa_twofactor_attempt';
    private const LOCK_SECONDS = 900;

    public function __construct(
        private ConnectionProviderInterface $connectionProvider,
        private ClockInterface $clock,
    ) {
    }

    public function reserveAttempt(string $userId): int
    {
        $this->forgetStale($userId);

        $connection = $this->connectionProvider->get();
        // LAST_INSERT_ID(expression) remembers the new counter value per connection, so every request gets its own
        // number even when many run at the same time (reading the column afterwards would return the latest value
        // of all of them). updated_at only moves while attempts are still granted, so hammering a locked account does
        // not prolong the lock. MySQL evaluates the assignments left to right: failures is already incremented.
        $connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (user_id, failures, updated_at) VALUES (:user, LAST_INSERT_ID(1), :created)
             ON DUPLICATE KEY UPDATE
                failures = LAST_INSERT_ID(failures + 1),
                updated_at = IF(failures <= :max, :updated, updated_at)',
            [
                'user' => $userId,
                'created' => $this->now(),
                'max' => ChallengeThrottle::MAX_ATTEMPTS,
                'updated' => $this->now(),
            ]
        );

        return (int)$connection->fetchOne('SELECT LAST_INSERT_ID()');
    }

    public function attempts(string $userId): int
    {
        // Read only: the admin overview asks for every account, and showing a page must not write.
        return (int)$this->connectionProvider->get()->fetchOne(
            'SELECT failures FROM ' . self::TABLE . ' WHERE user_id = :user AND updated_at > :cutoff',
            ['user' => $userId, 'cutoff' => $this->cutoff()]
        );
    }

    public function reset(string $userId): void
    {
        $this->connectionProvider->get()->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE user_id = :user',
            ['user' => $userId]
        );
    }

    private function forgetStale(string $userId): void
    {
        $this->connectionProvider->get()->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE user_id = :user AND updated_at <= :cutoff',
            ['user' => $userId, 'cutoff' => $this->cutoff()]
        );
    }

    /** Attempts last made at or before this moment are forgotten. */
    private function cutoff(): string
    {
        return UtcDateTime::format($this->clock->now()->modify('-' . self::LOCK_SECONDS . ' seconds'));
    }

    private function now(): string
    {
        return UtcDateTime::format($this->clock->now());
    }
}
