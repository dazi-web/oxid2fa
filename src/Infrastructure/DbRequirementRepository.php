<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Infrastructure;

use DateTimeImmutable;
use DaziWeb\Oxid2Fa\Application\RequirementRepository;
use OxidEsales\EshopCommunity\Internal\Framework\Database\ConnectionProviderInterface;

final readonly class DbRequirementRepository implements RequirementRepository
{
    private const TABLE = 'oxid2fa_required';

    public function __construct(private ConnectionProviderInterface $connectionProvider)
    {
    }

    public function isRequired(string $userId): bool
    {
        return (bool)$this->connectionProvider->get()->fetchOne(
            'SELECT 1 FROM ' . self::TABLE . ' WHERE user_id = :user',
            ['user' => $userId]
        );
    }

    public function requiredUserIds(): array
    {
        return array_values(array_map(
            'strval',
            $this->connectionProvider->get()->fetchFirstColumn('SELECT user_id FROM ' . self::TABLE)
        ));
    }

    public function setRequired(string $userId, bool $required): void
    {
        $connection = $this->connectionProvider->get();
        $parameters = ['user' => $userId];

        if ($required) {
            $connection->executeStatement(
                'INSERT IGNORE INTO ' . self::TABLE . ' (user_id, created_at) VALUES (:user, :now)',
                $parameters + ['now' => UtcDateTime::format(new DateTimeImmutable())]
            );

            return;
        }

        $connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE user_id = :user',
            $parameters
        );
    }
}
