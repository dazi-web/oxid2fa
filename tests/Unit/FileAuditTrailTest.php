<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit;

use DaziWeb\Oxid2Fa\Infrastructure\AuditLogFile;
use DaziWeb\Oxid2Fa\Infrastructure\FileAuditTrail;
use OxidEsales\EshopCommunity\Internal\Transition\Utility\ContextInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileAuditTrail::class)]
final class FileAuditTrailTest extends TestCase
{
    private string $directory;
    private string $logFile;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/oxid2fa-audit-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
        $this->logFile = $this->directory . '/oxid2fa_audit.log';
    }

    protected function tearDown(): void
    {
        @unlink($this->logFile);
        @rmdir($this->directory);
    }

    public function testReadsEntriesNewestFirstWithOrigin(): void
    {
        $this->write([
            $this->line('2026-10-01 10:00:00', '2FA_ENABLED', 'u1', 'u1', ['ip' => '203.0.113.7']),
            $this->line(
                '2026-10-01 10:05:00',
                '2FA_RESET',
                'u1',
                'u2',
                ['ip' => '203.0.113.8', 'forwarded_for' => '198.51.100.9']
            ),
        ]);

        $entries = $this->trail()->recent(10);

        $this->assertSame(['2FA_RESET', '2FA_ENABLED'], array_column(array_map('get_object_vars', $entries), 'event'));
        $this->assertSame('u2', $entries[0]->actorId);
        $this->assertSame('203.0.113.8 (198.51.100.9)', $entries[0]->origin);
        $this->assertSame('203.0.113.7', $entries[1]->origin);
        $this->assertSame('2026-10-01 10:05:00', $entries[0]->time);
    }

    public function testFiltersByAccountAndLimitsTheResult(): void
    {
        $this->write([
            $this->line('2026-10-01 10:00:00', '2FA_CHALLENGE_FAILED', 'u1', 'u1', []),
            $this->line('2026-10-01 10:01:00', '2FA_CHALLENGE_FAILED', 'u2', 'u2', []),
            $this->line('2026-10-01 10:02:00', '2FA_CHALLENGE_FAILED', 'u1', 'u1', []),
            $this->line('2026-10-01 10:03:00', '2FA_CHALLENGE_FAILED', 'u1', 'u1', []),
        ]);

        $this->assertCount(2, $this->trail()->recent(2, 'u1'));
        $this->assertCount(3, $this->trail()->recent(10, 'u1'));
        $this->assertCount(1, $this->trail()->recent(10, 'u2'));
        $this->assertSame('2026-10-01 10:03:00', $this->trail()->recent(1, 'u1')[0]->time);
    }

    public function testIgnoresLinesThatAreNotAuditEntries(): void
    {
        $this->write([
            'garbage',
            '[2026-10-01 10:00:00] TwoFactor Audit.NOTICE: 2FA_ENABLED not-json []',
            $this->line('2026-10-01 10:01:00', '2FA_ENABLED', 'u1', 'u1', []),
        ]);

        $this->assertCount(1, $this->trail()->recent(10));
    }

    public function testMissingOrEmptyLogIsNotAnError(): void
    {
        $this->assertSame([], $this->trail()->recent(10));

        file_put_contents($this->logFile, '');
        $this->assertSame([], $this->trail()->recent(10));
    }

    public function testOnlyTheEndOfAHugeFileIsRead(): void
    {
        $filler = str_repeat("filler line that is not an entry\n", 40000); // > 1 MB
        $entry = $this->line('2026-10-01 10:00:00', '2FA_ENABLED', 'u1', 'u1', []);
        file_put_contents($this->logFile, $filler . $entry . "\n");

        $entries = $this->trail()->recent(10);

        $this->assertCount(1, $entries);
    }

    /** @param list<string> $lines */
    private function write(array $lines): void
    {
        file_put_contents($this->logFile, implode("\n", $lines) . "\n");
    }

    /** @param array<string, string> $extra */
    private function line(string $time, string $event, string $user, string $actor, array $extra): string
    {
        $base = ['user_id' => $user, 'context' => 'admin', 'actor_id' => $actor];
        $context = json_encode($base + $extra, JSON_THROW_ON_ERROR);

        return sprintf('[%s] TwoFactor Audit.NOTICE: %s %s []', $time, $event, $context);
    }

    private function trail(): FileAuditTrail
    {
        $context = $this->createStub(ContextInterface::class);
        $context->method('getLogFilePath')->willReturn($this->directory . '/oxideshop.log');

        return new FileAuditTrail(new AuditLogFile($context));
    }
}
