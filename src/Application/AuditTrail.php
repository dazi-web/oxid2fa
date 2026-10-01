<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

interface AuditTrail
{
    /**
     * @param string|null $userId only events about this account
     *
     * @return list<AuditEntry> newest first
     */
    public function recent(int $limit, ?string $userId = null): array;
}
