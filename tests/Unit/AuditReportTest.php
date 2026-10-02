<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit;

use DaziWeb\Oxid2Fa\Application\AuditEntry;
use DaziWeb\Oxid2Fa\Application\AuditReport;
use DaziWeb\Oxid2Fa\Application\AuditTrail;
use DaziWeb\Oxid2Fa\Tests\Unit\Support\FakeUserDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuditReport::class)]
final class AuditReportTest extends TestCase
{
    public function testShowsLoginNamesInsteadOfIds(): void
    {
        $trail = new class implements AuditTrail {
            public function recent(int $limit, ?string $userId = null): array
            {
                return [
                    new AuditEntry('2026-10-01 10:00:00', '2FA_RESET', 'u1', 'u2', '203.0.113.7'),
                    new AuditEntry('2026-10-01 09:00:00', '2FA_RESET', 'u1', 'cli', ''),
                ];
            }
        };

        $rows = (new AuditReport($trail, new FakeUserDirectory()))->recent(10);

        $this->assertSame('u1@example.com', $rows[0]->account);
        $this->assertSame('u2@example.com', $rows[0]->actor);
        $this->assertSame('cli', $rows[1]->actor);
        $this->assertSame('203.0.113.7', $rows[0]->origin);
    }

    public function testTurnedDownSignInAttemptsAreMarkedAndNothingElse(): void
    {
        $trail = new class implements AuditTrail {
            public function recent(int $limit, ?string $userId = null): array
            {
                return array_map(
                    static fn (string $event): AuditEntry => new AuditEntry('2026-10-01 10:00', $event, 'u1', 'u1', ''),
                    [
                        '2FA_LOGIN_REFUSED',
                        '2FA_CHALLENGE_FAILED',
                        '2FA_CHALLENGE_LOCKED',
                        '2FA_ENABLED',
                        'RECOVERY_CODE_USED',
                        '2FA_NOT_OPERATIONAL',
                        'SOMETHING_FROM_A_NEWER_VERSION',
                    ]
                );
            }
        };

        $rows = (new AuditReport($trail, new FakeUserDirectory()))->recent(10);

        $this->assertSame(
            [true, true, true, false, false, false, false],
            array_map(static fn ($row): bool => $row->isRefusal(), $rows)
        );
    }
}
