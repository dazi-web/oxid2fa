<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Application;

use DaziWeb\Oxid2Fa\Domain\Enrollment;
use DaziWeb\Oxid2Fa\Infrastructure\SecretCipher;
use OTPHP\TOTP;
use Psr\Clock\ClockInterface;

final readonly class TotpVerifier
{
    private const PERIOD = 30;

    /** 160 bit as recommended by RFC 4226; longer secrets make QR codes unwieldy and trip up some apps. */
    private const SECRET_BYTES = 20;

    /** One step of tolerance in each direction covers normal clock drift; more only widens the guessing window. */
    private const TOLERATED_STEPS = 1;

    public function __construct(
        private ClockInterface $clock,
        private SecretCipher $cipher,
        private EnrollmentRepository $enrollments,
    ) {
    }

    public function generateSecret(): string
    {
        return TOTP::generate($this->clock, self::SECRET_BYTES)->getSecret();
    }

    /** The secret of an enrolment, for the one place that has to show it again: the setup page. */
    public function secretOf(Enrollment $enrollment): string
    {
        return $this->cipher->decrypt($enrollment->secretEncrypted);
    }

    /**
     * Checks a code against an enrolment and spends its time step, so that the same code cannot be used twice.
     */
    public function verify(Enrollment $enrollment, string $code): bool
    {
        $step = $this->matchingStep($this->secretOf($enrollment), $code);

        return $step !== null && $this->enrollments->claimStep($enrollment->identifier, $step);
    }

    public function provisioningUri(string $secret, string $issuer, string $accountLabel): string
    {
        if ($secret === '') {
            throw new \InvalidArgumentException('A provisioning URI needs a secret.');
        }

        $totp = TOTP::createFromSecret($secret, $this->clock);
        $totp->setIssuer(self::withoutColon($issuer, 'OXID eShop'));
        $totp->setLabel(self::withoutColon($accountLabel, 'admin'));

        return $totp->getProvisioningUri();
    }

    /**
     * The otpauth URI uses ":" to separate issuer and account, and OTPHP rejects it inside either part. A shop
     * name like "Shop: Outlet" must not make the setup (and with mandatory mode every login) fail.
     *
     * @param non-empty-string $fallback
     *
     * @return non-empty-string
     */
    private static function withoutColon(string $value, string $fallback): string
    {
        $clean = trim(preg_replace('/\s*:\s*/', ' - ', $value) ?? '');

        return $clean !== '' ? $clean : $fallback;
    }

    /**
     * @return int|null the matching time step (needed for replay protection), null if the code is wrong
     */
    public function matchingStep(string $secret, string $code): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if ($secret === '' || !preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $totp = TOTP::createFromSecret($secret, $this->clock);
        $currentStep = intdiv($this->clock->now()->getTimestamp(), self::PERIOD);

        $match = null;
        for ($offset = -self::TOLERATED_STEPS; $offset <= self::TOLERATED_STEPS; $offset++) {
            $step = $currentStep + $offset;
            // no early exit: keeps the run time independent of which step matched
            if (hash_equals($totp->at(max(0, $step * self::PERIOD)), $code)) {
                $match = $step;
            }
        }

        return $match;
    }
}
