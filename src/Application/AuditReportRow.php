<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

final readonly class AuditReportRow
{
    public function __construct(
        public string $time,
        public string $event,
        public string $account,
        public string $actor,
        public string $origin,
    ) {
    }
}
