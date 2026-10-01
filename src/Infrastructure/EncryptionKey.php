<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Infrastructure;

/**
 * OXID 7.3 offers no secret store (the core Encryptor is a reversible XOR obfuscation), so the key lives
 * outside the database that holds the encrypted secrets: in the environment (takes precedence) or in the
 * key file that the module creates when it is activated.
 */
final readonly class EncryptionKey
{
    public const ENVIRONMENT_VARIABLE = 'OXID2FA_ENCRYPTION_KEY';

    public const KEY_BYTES = 32;

    public function __construct(private ?string $raw)
    {
    }

    public static function resolve(EncryptionKeyFile $file): self
    {
        $fromEnvironment = self::fromEnvironment();

        return $fromEnvironment->isConfigured() ? $fromEnvironment : new self($file->read());
    }

    public static function fromEnvironment(): self
    {
        $value = getenv(self::ENVIRONMENT_VARIABLE);

        return new self(is_string($value) ? self::decode($value) : null);
    }

    /** @return string|null the raw key, null if the value is not a base64 encoded key of the right length */
    public static function decode(string $base64): ?string
    {
        $raw = base64_decode(trim($base64), true);

        return $raw !== false && strlen($raw) === self::KEY_BYTES ? $raw : null;
    }

    public function isConfigured(): bool
    {
        return $this->raw !== null;
    }

    public function forSecrets(): string
    {
        return $this->derive('totp-secret');
    }

    public function forRecoveryCodes(): string
    {
        return $this->derive('recovery-code');
    }

    private function derive(string $purpose): string
    {
        if ($this->raw === null) {
            throw new \RuntimeException(self::ENVIRONMENT_VARIABLE . ' is not configured.');
        }

        return sodium_crypto_generichash($purpose, $this->raw, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
