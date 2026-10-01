<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Domain;

use DateTimeImmutable;

final readonly class Enrollment
{
    public function __construct(
        public string $identifier,
        public string $userId,
        public string $secretEncrypted,
        public ?DateTimeImmutable $enabledAt,
        public ?int $lastUsedStep,
    ) {
    }

    public function isActive(): bool
    {
        return $this->enabledAt !== null;
    }
}
