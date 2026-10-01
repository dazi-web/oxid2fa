<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Infrastructure;

use OxidEsales\Facts\Facts;

/**
 * The key file lives in the shop's var directory: outside the document root and outside the database.
 * Back it up together with the database, and keep it out of version control.
 */
final readonly class EncryptionKeyFile
{
    private const RELATIVE_PATH = 'var/oxid2fa/encryption.key';

    public function __construct(private string $shopRootPath)
    {
    }

    public static function forShop(): self
    {
        return new self((new Facts())->getShopRootPath());
    }

    public function path(): string
    {
        return rtrim($this->shopRootPath, '/') . '/' . self::RELATIVE_PATH;
    }

    /** Relative to the shop root, for display. */
    public function displayPath(): string
    {
        return self::RELATIVE_PATH;
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    public function isRestrictedToOwner(): bool
    {
        $permissions = @fileperms($this->path());

        return $permissions !== false && ($permissions & 0077) === 0;
    }

    public function read(): ?string
    {
        $content = is_readable($this->path()) ? file_get_contents($this->path()) : false;

        return $content === false ? null : EncryptionKey::decode($content);
    }

    /**
     * Never overwrites: a replaced key would make every stored secret unreadable.
     *
     * @return bool true if a new key was written
     */
    public function createIfMissing(): bool
    {
        $directory = dirname($this->path());
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            return false;
        }

        // 'x': fails if the file exists, so two concurrent activations cannot both write a key
        $handle = @fopen($this->path(), 'xb');
        if ($handle === false) {
            return false;
        }

        chmod($this->path(), 0600);
        fwrite($handle, base64_encode(random_bytes(EncryptionKey::KEY_BYTES)) . "\n");
        fclose($handle);

        return true;
    }
}
