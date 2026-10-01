<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Infrastructure;

use DateTimeImmutable;
use OxidEsales\EshopCommunity\Internal\Framework\Database\ConnectionProviderInterface;
use DaziWeb\Oxid2Fa\Application\RecoveryCodeRepository;

final readonly class DbRecoveryCodeRepository implements RecoveryCodeRepository
{
    private const TABLE = 'oxid2fa_twofactor_recovery_code';

    public function __construct(private ConnectionProviderInterface $connectionProvider)
    {
    }

    public function replaceAll(string $enrollmentId, array $hashes): void
    {
        $connection = $this->connectionProvider->get();
        $now = UtcDateTime::format(new DateTimeImmutable());

        $connection->transactional(static function ($connection) use ($enrollmentId, $hashes, $now): void {
            $connection->executeStatement(
                'DELETE FROM ' . self::TABLE . ' WHERE two_factor_id = :id',
                ['id' => $enrollmentId]
            );
            foreach ($hashes as $hash) {
                $connection->executeStatement(
                    'INSERT INTO ' . self::TABLE . ' (id, two_factor_id, code_hash, created_at)
                     VALUES (:id, :enrollment, :hash, :now)',
                    ['id' => bin2hex(random_bytes(16)), 'enrollment' => $enrollmentId, 'hash' => $hash, 'now' => $now]
                );
            }
        });
    }

    public function consume(string $enrollmentId, string $hash, DateTimeImmutable $moment): bool
    {
        // The single UPDATE is the lock: only one of several parallel requests sees an affected row.
        $changed = $this->connectionProvider->get()->executeStatement(
            'UPDATE ' . self::TABLE . ' SET used_at = :at
             WHERE two_factor_id = :enrollment AND code_hash = :hash AND used_at IS NULL',
            [
                'at' => UtcDateTime::format($moment),
                'enrollment' => $enrollmentId,
                'hash' => $hash,
            ]
        );

        return $changed === 1;
    }

    public function countUnused(string $enrollmentId): int
    {
        return (int)$this->connectionProvider->get()->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE two_factor_id = :id AND used_at IS NULL',
            ['id' => $enrollmentId]
        );
    }
}
