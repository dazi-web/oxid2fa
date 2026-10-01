<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Database;

use DaziWeb\Oxid2Fa\Infrastructure\DbEnrollmentRepository;
use DaziWeb\Oxid2Fa\Infrastructure\DbRecoveryCodeRepository;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The guarantees that matter for security only hold if they survive real parallel requests. Each worker is a
 * separate PHP process with its own database connection; all of them fire at the same moment.
 */
#[CoversNothing]
final class ConcurrencyTest extends DatabaseTestCase
{
    private const WORKERS = 12;

    public function testParallelAttemptsAreNeverCountedTwice(): void
    {
        $results = $this->inParallel(self::WORKERS, 'attempt', ['user-1']);

        sort($results);
        $this->assertSame(range(1, self::WORKERS), array_map('intval', $results), 'every request got its own number');
        $this->assertSame(
            5,
            count(array_filter($results, static fn (string $number): bool => (int)$number <= 5)),
            'of any number of simultaneous guesses exactly five are within the budget'
        );
    }

    public function testAnActivationOnlyOneRequestWins(): void
    {
        $enrollmentId = (new DbEnrollmentRepository($this->provider))
            ->saveSetup('user-1', 'secret')->identifier;

        $results = $this->inParallel(self::WORKERS, 'activate', [$enrollmentId]);

        $this->assertSame(1, array_sum(array_map('intval', $results)));
    }

    public function testATimeStepOnlyOneRequestCanSpend(): void
    {
        $enrollmentId = (new DbEnrollmentRepository($this->provider))
            ->saveSetup('user-1', 'secret')->identifier;

        $results = $this->inParallel(self::WORKERS, 'claim', [$enrollmentId, '5000']);

        $winners = array_sum(array_map('intval', $results));
        $this->assertSame(1, $winners, 'the same TOTP code cannot be replayed in parallel');
    }

    public function testARecoveryCodeCanBeRedeemedByOnlyOneRequest(): void
    {
        $enrollmentId = (new DbEnrollmentRepository($this->provider))
            ->saveSetup('user-1', 'secret')->identifier;
        (new DbRecoveryCodeRepository($this->provider))->replaceAll($enrollmentId, ['hash-1', 'hash-2']);

        $results = $this->inParallel(self::WORKERS, 'consume', [$enrollmentId, 'hash-1']);

        $this->assertSame(1, array_sum(array_map('intval', $results)));
        $this->assertSame(1, (new DbRecoveryCodeRepository($this->provider))->countUnused($enrollmentId));
    }

    /**
     * @param list<string> $arguments
     *
     * @return list<string> the output of each worker
     */
    private function inParallel(int $workers, string $operation, array $arguments): array
    {
        $startAt = microtime(true) + 1.0;
        $processes = [];
        for ($i = 0; $i < $workers; $i++) {
            $command = array_merge([PHP_BINARY, __DIR__ . '/worker.php', (string)$startAt, $operation], $arguments);
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $this->assertIsResource($process);
            $processes[] = [$process, $pipes];
        }

        $results = [];
        foreach ($processes as [$process, $pipes]) {
            $output = trim((string)stream_get_contents($pipes[1]));
            $errors = trim((string)stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), 'worker failed: ' . $errors);
            $results[] = $output;
        }

        return $results;
    }
}
