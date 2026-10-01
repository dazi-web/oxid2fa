<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit;

use DaziWeb\Oxid2Fa\Infrastructure\EncryptionKey;
use DaziWeb\Oxid2Fa\Infrastructure\SecretCipher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecretCipher::class)]
#[CoversClass(EncryptionKey::class)]
final class SecretCipherTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $cipher = new SecretCipher(new EncryptionKey(str_repeat('a', 32)));

        $stored = $cipher->encrypt('JBSWY3DPEHPK3PXP');

        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $stored);
        $this->assertSame('JBSWY3DPEHPK3PXP', $cipher->decrypt($stored));
    }

    public function testSameSecretEncryptsDifferentlyEachTime(): void
    {
        $cipher = new SecretCipher(new EncryptionKey(str_repeat('a', 32)));

        $this->assertNotSame($cipher->encrypt('SECRET'), $cipher->encrypt('SECRET'));
    }

    public function testWrongKeyCannotDecrypt(): void
    {
        $stored = (new SecretCipher(new EncryptionKey(str_repeat('a', 32))))->encrypt('SECRET');

        $this->expectException(\RuntimeException::class);
        (new SecretCipher(new EncryptionKey(str_repeat('b', 32))))->decrypt($stored);
    }

    public function testTamperedDataIsRejectedWithoutLeakingTheSecret(): void
    {
        $cipher = new SecretCipher(new EncryptionKey(str_repeat('a', 32)));
        $stored = $cipher->encrypt('SECRET');
        $tampered = substr($stored, 0, -4) . 'AAAA';

        try {
            $cipher->decrypt($tampered);
            $this->fail('Expected exception');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('SECRET', $e->getMessage());
        }
    }

    public function testMissingKeyIsReportedAndCannotBeUsed(): void
    {
        $key = new EncryptionKey(null);

        $this->assertFalse($key->isConfigured());
        $this->expectException(\RuntimeException::class);
        (new SecretCipher($key))->encrypt('SECRET');
    }
}
