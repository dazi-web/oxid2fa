<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit;

use OTPHP\TOTP;
use DaziWeb\Oxid2Fa\Application\TotpVerifier;
use DaziWeb\Oxid2Fa\Infrastructure\EncryptionKey;
use DaziWeb\Oxid2Fa\Infrastructure\SecretCipher;
use DaziWeb\Oxid2Fa\Tests\Unit\Support\InMemoryEnrollmentRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(TotpVerifier::class)]
final class TotpVerifierTest extends TestCase
{
    private MockClock $clock;
    private TotpVerifier $verifier;
    private string $secret;
    private SecretCipher $cipher;
    private InMemoryEnrollmentRepository $enrollments;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-10-01 12:00:00');
        $this->cipher = new SecretCipher(new EncryptionKey(str_repeat('k', 32)));
        $this->enrollments = new InMemoryEnrollmentRepository();
        $this->verifier = new TotpVerifier($this->clock, $this->cipher, $this->enrollments);
        $this->secret = $this->verifier->generateSecret();
    }

    public function testVerifyingAnEnrolmentSpendsTheTimeStep(): void
    {
        // Given an enrolment with this secret
        $encrypted = $this->cipher->encrypt($this->secret);
        $enrollment = $this->enrollments->saveSetup('u1', $encrypted);
        $code = $this->codeAt(0);

        // When the same code is presented twice
        $first = $this->verifier->verify($enrollment, $code);
        $second = $this->verifier->verify($enrollment, $code);

        // Then only the first use counts
        $this->assertTrue($first);
        $this->assertFalse($second);
    }

    public function testGeneratedSecretHasTheRecommendedLength(): void
    {
        $this->assertSame(32, strlen($this->secret));
    }

    public function testAcceptsCurrentCode(): void
    {
        // Given an authenticator showing the current code
        $code = $this->codeAt(0);

        // When / Then it is accepted for exactly the current time step
        $this->assertSame($this->currentStep(), $this->verifier->matchingStep($this->secret, $code));
    }

    public function testProvisioningUriSurvivesColonsInIssuerAndAccount(): void
    {
        $uri = $this->verifier->provisioningUri($this->secret, 'Shop: Outlet', 'a:b@example.com');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('issuer=Shop%20-%20Outlet', $uri);
    }

    public function testAcceptsCodeWithSpacesAsAppsDisplayIt(): void
    {
        $code = $this->codeAt(0);

        $spaced = substr($code, 0, 3) . ' ' . substr($code, 3);

        $this->assertSame($this->currentStep(), $this->verifier->matchingStep($this->secret, $spaced));
    }

    public function testRejectsWrongCode(): void
    {
        $wrong = $this->codeAt(0) === '000000' ? '000001' : '000000';

        $this->assertNull($this->verifier->matchingStep($this->secret, $wrong));
    }

    public function testRejectsCodeFromAnotherSecret(): void
    {
        $otherSecret = $this->verifier->generateSecret();

        $foreignCode = TOTP::createFromSecret($otherSecret, $this->clock)->now();

        $this->assertNull($this->verifier->matchingStep($this->secret, $foreignCode));
    }

    public function testRejectsMalformedInput(): void
    {
        foreach (['', 'abcdef', '12345', '1234567'] as $input) {
            $this->assertNull($this->verifier->matchingStep($this->secret, $input), $input);
        }
    }

    public function testToleratesOneStepOfClockDriftInEitherDirection(): void
    {
        $this->assertSame($this->currentStep() - 1, $this->verifier->matchingStep($this->secret, $this->codeAt(-30)));
        $this->assertSame($this->currentStep() + 1, $this->verifier->matchingStep($this->secret, $this->codeAt(30)));
    }

    public function testRejectsCodesTwoStepsAwayBecauseTheWindowIsConservative(): void
    {
        $this->assertNull($this->verifier->matchingStep($this->secret, $this->codeAt(-60)));
        $this->assertNull($this->verifier->matchingStep($this->secret, $this->codeAt(60)));
    }

    public function testRejectsCodeOutsideTheWindow(): void
    {
        $this->assertNull($this->verifier->matchingStep($this->secret, $this->codeAt(-90)));
        $this->assertNull($this->verifier->matchingStep($this->secret, $this->codeAt(90)));
    }

    public function testCodeExpiresAsTimePasses(): void
    {
        // Given a code that is valid now
        $code = $this->codeAt(0);

        // When two minutes pass without sleeping
        $this->clock->sleep(120);

        // Then it is no longer accepted
        $this->assertNull($this->verifier->matchingStep($this->secret, $code));
    }

    public function testReportsTheStepSoThatReplayCanBeDetected(): void
    {
        $step = $this->verifier->matchingStep($this->secret, $this->codeAt(0));

        $this->assertSame(intdiv($this->clock->now()->getTimestamp(), 30), $step);
    }

    public function testProvisioningUriCarriesIssuerAndAccount(): void
    {
        $uri = $this->verifier->provisioningUri($this->secret, 'Testshop', 'admin@example.com');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('issuer=Testshop', $uri);
        $this->assertStringContainsString('secret=' . $this->secret, $uri);
    }

    private function currentStep(): int
    {
        return intdiv($this->clock->now()->getTimestamp(), 30);
    }

    private function codeAt(int $offsetSeconds): string
    {
        $timestamp = $this->clock->now()->getTimestamp() + $offsetSeconds;

        return TOTP::createFromSecret($this->secret, $this->clock)->at($timestamp);
    }
}
