<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Infrastructure;

final readonly class SecretCipher
{
    private const VERSION = 'v1:';

    public function __construct(private EncryptionKey $key)
    {
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plain, $nonce, $this->key->forSecrets());

        return self::VERSION . base64_encode($nonce . $cipher);
    }

    public function decrypt(string $stored): string
    {
        $decoded = str_starts_with($stored, self::VERSION)
            ? base64_decode(substr($stored, strlen(self::VERSION)), true)
            : false;

        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Stored two-factor secret is not readable.');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(
            substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $nonce,
            $this->key->forSecrets()
        );

        if ($plain === false) {
            throw new \RuntimeException('Stored two-factor secret could not be decrypted.');
        }

        return $plain;
    }
}
