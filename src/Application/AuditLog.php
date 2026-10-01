<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

use DaziWeb\Oxid2Fa\Domain\AuditEvent;
use Psr\Log\LoggerInterface;

/**
 * Deliberately takes no codes or secrets: only who did what to whose account, and from where.
 */
final readonly class AuditLog
{
    public function __construct(
        private LoggerInterface $logger,
        private RequestContext $request,
    ) {
    }

    public function record(AuditEvent $event, string $userId, ?string $actorId = null): void
    {
        $this->logger->notice($event->value, [
            'user_id' => $userId,
            'actor_id' => $actorId ?? $userId,
        ] + $this->request->origin());
    }
}
