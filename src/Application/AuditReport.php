<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

/**
 * The audit log for the admin pages: the same entries, with user ids replaced by login names.
 */
final readonly class AuditReport
{
    private const SYSTEM_ACTOR = 'cli';

    public function __construct(
        private AuditTrail $trail,
        private UserDirectory $users,
    ) {
    }

    /** @return list<AuditReportRow> */
    public function recent(int $limit, ?string $userId = null): array
    {
        return array_map(fn (AuditEntry $entry): AuditReportRow => new AuditReportRow(
            $entry->time,
            $entry->event,
            $this->users->accountLabel($entry->userId),
            $entry->actorId === self::SYSTEM_ACTOR ? self::SYSTEM_ACTOR : $this->users->accountLabel($entry->actorId),
            $entry->origin,
        ), $this->trail->recent($limit, $userId));
    }
}
