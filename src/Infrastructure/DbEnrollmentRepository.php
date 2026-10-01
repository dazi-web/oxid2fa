<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Infrastructure;

use DateTimeImmutable;
use OxidEsales\EshopCommunity\Internal\Framework\Database\ConnectionProviderInterface;
use DaziWeb\Oxid2Fa\Application\EnrollmentRepository;
use DaziWeb\Oxid2Fa\Domain\Enrollment;

final readonly class DbEnrollmentRepository implements EnrollmentRepository
{
    public const TABLE = 'oxid2fa_twofactor';
    public function __construct(private ConnectionProviderInterface $connectionProvider)
    {
    }

    public function find(string $userId): ?Enrollment
    {
        $row = $this->connectionProvider->get()->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE user_id = :user',
            ['user' => $userId]
        );

        return $row === false ? null : $this->hydrate($row);
    }

    public function saveSetup(string $userId, string $secretEncrypted): Enrollment
    {
        $connection = $this->connectionProvider->get();
        $identifier = bin2hex(random_bytes(16));
        $now = UtcDateTime::format(new DateTimeImmutable());

        // A finished enrolment is protected by the WHERE of the update: setup can only overwrite setup.
        $connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (id, user_id, secret_encrypted, created_at, updated_at)
             VALUES (:id, :user, :secret, :created, :updated)
             ON DUPLICATE KEY UPDATE
                secret_encrypted = IF(enabled_at IS NULL, VALUES(secret_encrypted), secret_encrypted),
                last_used_step = IF(enabled_at IS NULL, NULL, last_used_step),
                updated_at = IF(enabled_at IS NULL, VALUES(updated_at), updated_at)',
            [
                'id' => $identifier,
                'user' => $userId,
                'secret' => $secretEncrypted,
                'created' => $now,
                'updated' => $now,
            ]
        );

        return $this->find($userId) ?? throw new \RuntimeException('Enrolment was not stored.');
    }

    public function activate(string $enrollmentId, DateTimeImmutable $moment): bool
    {
        $changed = $this->connectionProvider->get()->executeStatement(
            'UPDATE ' . self::TABLE . ' SET enabled_at = :enabled, updated_at = :updated
             WHERE id = :id AND enabled_at IS NULL',
            ['enabled' => $utc = UtcDateTime::format($moment), 'updated' => $utc, 'id' => $enrollmentId]
        );

        return $changed === 1;
    }

    public function claimStep(string $enrollmentId, int $step): bool
    {
        $changed = $this->connectionProvider->get()->executeStatement(
            'UPDATE ' . self::TABLE . ' SET last_used_step = :step
             WHERE id = :id AND (last_used_step IS NULL OR last_used_step < :previous)',
            ['step' => $step, 'previous' => $step, 'id' => $enrollmentId]
        );

        return $changed === 1;
    }

    public function findAll(): array
    {
        $rows = $this->connectionProvider->get()->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE
        );

        $byUser = [];
        foreach ($rows as $row) {
            $byUser[(string)$row['user_id']] = $this->hydrate($row);
        }

        return $byUser;
    }

    public function allEncryptedSecrets(): array
    {
        return array_map(
            'strval',
            array_values(
                $this->connectionProvider->get()->fetchFirstColumn('SELECT secret_encrypted FROM ' . self::TABLE)
            )
        );
    }

    public function delete(string $userId): void
    {
        // recovery codes follow through ON DELETE CASCADE
        $this->connectionProvider->get()->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE user_id = :user',
            ['user' => $userId]
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Enrollment
    {
        return new Enrollment(
            (string)$row['id'],
            (string)$row['user_id'],
            (string)$row['secret_encrypted'],
            $row['enabled_at'] === null ? null : UtcDateTime::parse((string)$row['enabled_at']),
            $row['last_used_step'] === null ? null : (int)$row['last_used_step'],
        );
    }
}
