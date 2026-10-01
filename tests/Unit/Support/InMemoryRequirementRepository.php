<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit\Support;

use DaziWeb\Oxid2Fa\Application\RequirementRepository;

final class InMemoryRequirementRepository implements RequirementRepository
{
    /** @var array<string, true> */
    private array $required = [];

    public function isRequired(string $userId): bool
    {
        return isset($this->required[$userId]);
    }

    public function requiredUserIds(): array
    {
        return array_map('strval', array_keys($this->required));
    }

    public function setRequired(string $userId, bool $required): void
    {
        if ($required) {
            $this->required[$userId] = true;
        } else {
            unset($this->required[$userId]);
        }
    }
}
