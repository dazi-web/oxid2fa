<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit;

use DaziWeb\Oxid2Fa\Application\KeyHealthChecker;
use DaziWeb\Oxid2Fa\Application\KeySource;
use DaziWeb\Oxid2Fa\Infrastructure\EncryptionKey;
use DaziWeb\Oxid2Fa\Infrastructure\EncryptionKeyFile;
use DaziWeb\Oxid2Fa\Infrastructure\SecretCipher;
use DaziWeb\Oxid2Fa\Tests\Unit\Support\InMemoryEnrollmentRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KeyHealthChecker::class)]
final class KeyHealthCheckerTest extends TestCase
{
    private string $root;
    private EncryptionKeyFile $file;
    private InMemoryEnrollmentRepository $enrollments;

    protected function setUp(): void
    {
        putenv(EncryptionKey::ENVIRONMENT_VARIABLE);
        $this->root = sys_get_temp_dir() . '/oxid2fa-health-' . bin2hex(random_bytes(6));
        mkdir($this->root);
        $this->file = new EncryptionKeyFile($this->root);
        $this->enrollments = new InMemoryEnrollmentRepository();
    }

    protected function tearDown(): void
    {
        @unlink($this->file->path());
        @rmdir(dirname($this->file->path()));
        @rmdir($this->root . '/var');
        @rmdir($this->root);
    }

    public function testNoKeyAtAll(): void
    {
        $health = $this->inspect();

        $this->assertSame(KeySource::None, $health->source);
        $this->assertFalse($health->isHealthy());
        $this->assertFalse($health->keyFileInvalid);
    }

    public function testBrokenKeyFileIsReportedAsInvalidRatherThanMissing(): void
    {
        mkdir(dirname($this->file->path()), 0700, true);
        file_put_contents($this->file->path(), 'garbage');

        $health = $this->inspect();

        $this->assertSame(KeySource::None, $health->source);
        $this->assertTrue($health->keyFileInvalid);
    }

    public function testHealthyFileKeyWithReadableSecrets(): void
    {
        $this->file->createIfMissing();
        $this->enrollWithKey($this->file->read() ?? '', 'admin-1');
        $this->enrollWithKey($this->file->read() ?? '', 'admin-2');

        $health = $this->inspect();

        $this->assertSame(KeySource::File, $health->source);
        $this->assertTrue($health->isHealthy());
        $this->assertSame(2, $health->secretsReadable());
        $this->assertSame(2, $health->secretsTotal);
    }

    public function testSecretsEncryptedWithAnotherKeyAreCountedAsUnreadable(): void
    {
        $this->file->createIfMissing();
        $this->enrollWithKey($this->file->read() ?? '', 'admin-1');
        $this->enrollWithKey(random_bytes(EncryptionKey::KEY_BYTES), 'admin-2');

        $health = $this->inspect();

        $this->assertFalse($health->isHealthy());
        $this->assertSame(1, $health->secretsUnreadable);
        $this->assertSame(1, $health->secretsReadable());
    }

    public function testKeyFileReadableByOthersIsFlagged(): void
    {
        $this->file->createIfMissing();
        chmod($this->file->path(), 0644);

        $health = $this->inspect();

        $this->assertTrue($health->keyFileTooOpen);
        $this->assertFalse($health->isHealthy());
    }

    public function testKeyFromTheEnvironmentIsRecognised(): void
    {
        putenv(EncryptionKey::ENVIRONMENT_VARIABLE . '=' . base64_encode(random_bytes(EncryptionKey::KEY_BYTES)));

        try {
            $health = $this->inspect();
        } finally {
            putenv(EncryptionKey::ENVIRONMENT_VARIABLE);
        }

        $this->assertSame(KeySource::Environment, $health->source);
        $this->assertTrue($health->isHealthy());
    }

    private function inspect(): \DaziWeb\Oxid2Fa\Application\KeyHealth
    {
        $cipher = new SecretCipher(EncryptionKey::resolve($this->file));

        return (new KeyHealthChecker($this->file, $cipher, $this->enrollments))->inspect();
    }

    private function enrollWithKey(string $rawKey, string $userId): void
    {
        $secret = (new SecretCipher(new EncryptionKey($rawKey)))->encrypt('JBSWY3DPEHPK3PXP');
        $this->enrollments->saveSetup($userId, $secret);
    }
}
