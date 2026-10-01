<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

final readonly class KeyHealth
{
    public function __construct(
        public KeySource $source,
        public string $keyFilePath,
        public bool $keyFileInvalid,
        public bool $keyFileTooOpen,
        public int $secretsTotal,
        public int $secretsUnreadable,
    ) {
    }

    public function hasKey(): bool
    {
        return $this->source !== KeySource::None;
    }

    public function isHealthy(): bool
    {
        return $this->hasKey() && !$this->keyFileTooOpen && $this->secretsUnreadable === 0;
    }

    public function secretsReadable(): int
    {
        return $this->secretsTotal - $this->secretsUnreadable;
    }
}
