<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Integration\Oxid;

use DaziWeb\Oxid2Fa\Application\UserAccount;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\EshopCommunity\Internal\Framework\Database\ConnectionProviderInterface;
use DaziWeb\Oxid2Fa\Application\UserDirectory;

final class OxidUserDirectory implements UserDirectory
{
    public function __construct(private readonly ConnectionProviderInterface $connectionProvider)
    {
    }

    /** @var array<string, bool> one request asks about the same user several times */
    private array $activeByUser = [];

    /** @var array<string, string> the audit log shows the same few accounts over and over */
    private array $labelByUser = [];

    public function administrators(): array
    {
        $rows = $this->connectionProvider->get()->fetchAllAssociative(
            "SELECT oxid, oxusername FROM oxuser WHERE oxrights != 'user' AND oxactive = 1 ORDER BY oxusername"
        );

        return array_values(array_map(
            static fn (array $row): UserAccount => new UserAccount((string)$row['oxid'], (string)$row['oxusername']),
            $rows
        ));
    }

    public function isActive(string $userId): bool
    {
        if (!isset($this->activeByUser[$userId])) {
            $user = $this->load($userId);
            $this->activeByUser[$userId] = $user !== null && (bool)$user->getFieldData('oxactive');
        }

        return $this->activeByUser[$userId];
    }

    public function accountLabel(string $userId): string
    {
        if (!isset($this->labelByUser[$userId])) {
            $user = $this->load($userId);
            $this->labelByUser[$userId] = $user === null ? $userId : (string)$user->getFieldData('oxusername');
        }

        return $this->labelByUser[$userId];
    }

    private function load(string $userId): ?User
    {
        $user = oxNew(User::class);

        return $user->load($userId) ? $user : null;
    }
}
