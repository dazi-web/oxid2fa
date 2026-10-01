<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

use DaziWeb\Oxid2Fa\Infrastructure\EncryptionKey;
use DaziWeb\Oxid2Fa\Infrastructure\EncryptionKeyFile;
use DaziWeb\Oxid2Fa\Infrastructure\SecretCipher;

/**
 * Answers "is everything fine with the key?" for the settings page: where it comes from, whether the key file
 * is protected, and, the part that matters most, whether the stored secrets can still be decrypted with it.
 */
final readonly class KeyHealthChecker
{
    public function __construct(
        private EncryptionKeyFile $keyFile,
        private SecretCipher $cipher,
        private EnrollmentRepository $enrollments,
    ) {
    }

    public function inspect(): KeyHealth
    {
        $fileKeyUsable = $this->keyFile->read() !== null;
        $source = match (true) {
            EncryptionKey::fromEnvironment()->isConfigured() => KeySource::Environment,
            $fileKeyUsable => KeySource::File,
            default => KeySource::None,
        };

        $secrets = $this->enrollments->allEncryptedSecrets();

        return new KeyHealth(
            $source,
            $this->keyFile->displayPath(),
            $source === KeySource::None && $this->keyFile->exists(),
            $source === KeySource::File && !$this->keyFile->isRestrictedToOwner(),
            count($secrets),
            $source === KeySource::None ? 0 : $this->countUnreadable($secrets),
        );
    }

    /** @param list<string> $secrets */
    private function countUnreadable(array $secrets): int
    {
        $unreadable = 0;
        foreach ($secrets as $secret) {
            try {
                $this->cipher->decrypt($secret);
            } catch (\RuntimeException) {
                $unreadable++;
            }
        }

        return $unreadable;
    }
}
