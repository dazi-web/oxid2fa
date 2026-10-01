<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit\Support;

use DateTimeImmutable;
use DaziWeb\Oxid2Fa\Application\EnrollmentRepository;
use DaziWeb\Oxid2Fa\Domain\Enrollment;

final class InMemoryEnrollmentRepository implements EnrollmentRepository
{
    /** @var array<string, Enrollment> */
    private array $rows = [];

    public bool $unavailable = false;

    public function copyFrom(self $other): void
    {
        $this->rows = $other->rows;
    }

    public function find(string $userId): ?Enrollment
    {
        if ($this->unavailable) {
            throw new \RuntimeException('database unavailable');
        }

        foreach ($this->rows as $row) {
            if ($row->userId === $userId) {
                return $row;
            }
        }

        return null;
    }

    public function saveSetup(string $userId, string $secretEncrypted): Enrollment
    {
        $existing = $this->find($userId);
        if ($existing?->isActive()) {
            return $existing;
        }

        $id = $existing->identifier ?? 'enrollment-' . (count($this->rows) + 1);

        return $this->rows[$id] = new Enrollment($id, $userId, $secretEncrypted, null, null);
    }

    public function activate(string $id, DateTimeImmutable $at): bool
    {
        $row = $this->rows[$id] ?? null;
        if ($row === null || $row->isActive()) {
            return false;
        }
        $this->rows[$id] = $this->with($row, $at, $row->lastUsedStep);

        return true;
    }

    public function claimStep(string $id, int $step): bool
    {
        $row = $this->rows[$id] ?? null;
        if ($row === null || ($row->lastUsedStep !== null && $row->lastUsedStep >= $step)) {
            return false;
        }
        $this->rows[$id] = $this->with($row, $row->enabledAt, $step);

        return true;
    }

    public function findAll(): array
    {
        $byUser = [];
        foreach ($this->rows as $row) {
            $byUser[$row->userId] = $row;
        }

        return $byUser;
    }

    public function allEncryptedSecrets(): array
    {
        return array_values(array_map(static fn (Enrollment $row) => $row->secretEncrypted, $this->rows));
    }

    public function delete(string $userId): void
    {
        foreach ($this->rows as $id => $row) {
            if ($row->userId === $userId) {
                unset($this->rows[$id]);
            }
        }
    }

    private function with(Enrollment $row, ?DateTimeImmutable $enabledAt, ?int $lastUsedStep): Enrollment
    {
        return new Enrollment(
            $row->identifier,
            $row->userId,
            $row->secretEncrypted,
            $enabledAt,
            $lastUsedStep
        );
    }
}
