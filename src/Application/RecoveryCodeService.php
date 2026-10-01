<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

use DaziWeb\Oxid2Fa\Infrastructure\EncryptionKey;
use Psr\Clock\ClockInterface;

final readonly class RecoveryCodeService
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const LENGTH = 10;

    /** From this many unused codes downwards the admin is told to create new ones. */
    public const LOW_WATERMARK = 2;

    public function __construct(
        private RecoveryCodeRepository $codes,
        private EncryptionKey $key,
        private TwoFactorSettings $settings,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Replaces all existing codes. The plain codes are returned once and never stored; nothing is issued
     * if the shop does not use recovery codes.
     *
     * @return list<string>
     */
    public function issue(string $enrollmentId): array
    {
        if (!$this->settings->recoveryCodesEnabled()) {
            return [];
        }

        $count = $this->settings->recoveryCodeCount();
        $plain = [];
        for ($issued = 0; $issued < $count;) {
            $code = self::randomCode();
            if (!in_array($code, $plain, true)) {
                $plain[] = $code;
                $issued++;
            }
        }

        $this->codes->replaceAll($enrollmentId, array_map($this->hash(...), $plain));

        return array_map(self::format(...), $plain);
    }

    public function consume(string $enrollmentId, string $input): bool
    {
        if (!$this->settings->recoveryCodesEnabled()) {
            return false;
        }

        $normalized = self::normalize($input);
        if (strlen($normalized) !== self::LENGTH) {
            return false;
        }

        return $this->codes->consume($enrollmentId, $this->hash($normalized), $this->clock->now());
    }

    public function isEnabled(): bool
    {
        return $this->settings->recoveryCodesEnabled();
    }

    public function remaining(string $enrollmentId): int
    {
        return $this->codes->countUnused($enrollmentId);
    }

    private function hash(string $normalizedCode): string
    {
        // Codes carry ~50 bit of randomness, so a keyed fast hash is sufficient and allows an indexed,
        // atomic single-use lookup; a slow password hash would only make every attempt expensive.
        return hash_hmac('sha256', $normalizedCode, $this->key->forRecoveryCodes());
    }

    private static function randomCode(): string
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    private static function format(string $code): string
    {
        return substr($code, 0, 5) . '-' . substr($code, 5);
    }

    private static function normalize(string $input): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($input)) ?? '';
    }
}
