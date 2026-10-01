<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Tests\Unit;

use DaziWeb\Oxid2Fa\Infrastructure\EncryptionKey;
use DaziWeb\Oxid2Fa\Infrastructure\EncryptionKeyFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EncryptionKeyFile::class)]
#[CoversClass(EncryptionKey::class)]
final class EncryptionKeyFileTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/oxid2fa-test-' . bin2hex(random_bytes(6));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        $file = new EncryptionKeyFile($this->root);
        @unlink($file->path());
        @rmdir(dirname($file->path()));
        @rmdir($this->root . '/var');
        @rmdir($this->root);
    }

    public function testCreatesAUsableKeyWithRestrictedPermissions(): void
    {
        $file = new EncryptionKeyFile($this->root);

        $this->assertNull($file->read());
        $this->assertTrue($file->createIfMissing());

        $this->assertSame(EncryptionKey::KEY_BYTES, strlen((string)$file->read()));
        $this->assertSame('0600', substr(sprintf('%o', fileperms($file->path())), -4));
        $this->assertSame('0700', substr(sprintf('%o', fileperms(dirname($file->path()))), -4));
    }

    public function testNeverReplacesAnExistingKey(): void
    {
        $file = new EncryptionKeyFile($this->root);
        $file->createIfMissing();
        $before = $file->read();

        $this->assertFalse($file->createIfMissing());

        $this->assertSame($before, $file->read());
    }

    public function testAKeyOfTheWrongLengthOrFormatIsNotAccepted(): void
    {
        $file = new EncryptionKeyFile($this->root);
        mkdir(dirname($file->path()), 0700, true);

        file_put_contents($file->path(), base64_encode('too short'));
        $this->assertNull($file->read());

        file_put_contents($file->path(), 'not base64 !!!');
        $this->assertNull($file->read());
    }

    public function testDecodeAcceptsOnlyAFullLengthBase64Key(): void
    {
        $raw = random_bytes(EncryptionKey::KEY_BYTES);

        $this->assertSame($raw, EncryptionKey::decode(base64_encode($raw) . "\n"));
        $this->assertNull(EncryptionKey::decode(''));
        $this->assertNull(EncryptionKey::decode(base64_encode(random_bytes(16))));
    }

    public function testFileKeyIsUsedWhenTheEnvironmentHasNone(): void
    {
        putenv(EncryptionKey::ENVIRONMENT_VARIABLE);
        $file = new EncryptionKeyFile($this->root);
        $file->createIfMissing();

        $this->assertEquals(new EncryptionKey($file->read()), EncryptionKey::resolve($file));
    }

    public function testEnvironmentKeyTakesPrecedenceOverTheFile(): void
    {
        $file = new EncryptionKeyFile($this->root);
        $file->createIfMissing();
        $envKey = random_bytes(EncryptionKey::KEY_BYTES);
        putenv(EncryptionKey::ENVIRONMENT_VARIABLE . '=' . base64_encode($envKey));

        try {
            $resolved = EncryptionKey::resolve($file);
        } finally {
            putenv(EncryptionKey::ENVIRONMENT_VARIABLE);
        }

        $this->assertEquals(new EncryptionKey($envKey), $resolved);
    }
}
