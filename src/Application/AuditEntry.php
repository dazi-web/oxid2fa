<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

/**
 * One line of the audit log.
 */
final readonly class AuditEntry
{
    public function __construct(
        public string $time,
        public string $event,
        public string $userId,
        public string $actorId,
        public string $origin,
    ) {
    }
}
