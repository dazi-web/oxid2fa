<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

use DaziWeb\Oxid2Fa\Domain\AuditEvent;

/**
 * For main administrators: who has 2FA, who is required to have it, and who is locked out after failed attempts.
 */
final readonly class AdminOverview
{
    public function __construct(
        private UserDirectory $users,
        private EnrollmentRepository $enrollments,
        private RequirementRepository $requirements,
        private ChallengeThrottle $throttle,
        private ApiTokenRevocation $apiTokens,
        private AuditLog $audit,
    ) {
    }

    /** @return list<AdminOverviewRow> */
    public function rows(): array
    {
        $enrollments = $this->enrollments->findAll();
        $required = array_flip($this->requirements->requiredUserIds());

        return array_map(function (UserAccount $account) use ($enrollments, $required): AdminOverviewRow {
            $enrollment = $enrollments[$account->userId] ?? null;
            $status = match (true) {
                $enrollment === null => AdminOverviewRow::STATUS_NONE,
                $enrollment->isActive() => AdminOverviewRow::STATUS_ACTIVE,
                default => AdminOverviewRow::STATUS_SETUP,
            };

            return new AdminOverviewRow(
                $account->userId,
                $account->loginName,
                $status,
                $enrollment?->enabledAt,
                isset($required[$account->userId]),
                $this->throttle->attempts($account->userId),
            );
        }, $this->users->administrators());
    }

    public function setRequired(string $userId, bool $required, string $actorId): void
    {
        if (!$this->isAdministrator($userId)) {
            return;
        }

        $this->requirements->setRequired($userId, $required);
        if ($required) {
            $this->apiTokens->revokeAllFor($userId);
        }
        $this->audit->record(
            $required ? AuditEvent::RequiredSet : AuditEvent::RequiredCleared,
            $userId,
            $actorId
        );
    }

    public function unlock(string $userId, string $actorId): void
    {
        if (!$this->isAdministrator($userId)) {
            return;
        }

        $this->throttle->reset($userId);
        $this->audit->record(AuditEvent::Unlocked, $userId, $actorId);
    }

    private function isAdministrator(string $userId): bool
    {
        foreach ($this->users->administrators() as $account) {
            if ($account->userId === $userId) {
                return true;
            }
        }

        return false;
    }
}
