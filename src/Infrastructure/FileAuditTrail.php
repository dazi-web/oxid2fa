<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Infrastructure;

use DaziWeb\Oxid2Fa\Application\AuditEntry;
use DaziWeb\Oxid2Fa\Application\AuditTrail;

/**
 * Reads the audit log file written by AuditLog. Only the end of the file is read, so a big log stays cheap.
 */
final readonly class FileAuditTrail implements AuditTrail
{
    private const TAIL_BYTES = 524288;

    /** [2026-10-01 10:39:46] TwoFactor Audit.NOTICE: 2FA_CHALLENGE_FAILED {"user_id":"...",...} [] */
    private const LINE = '/^\[(?<time>[^\]]+)\] [^:]+: (?<event>[A-Z0-9_]+) (?<context>\{.*\}) \[\]$/';

    public function __construct(private AuditLogFile $file)
    {
    }

    public function recent(int $limit, ?string $userId = null): array
    {
        $entries = [];
        foreach (array_reverse($this->tailLines()) as $line) {
            $entry = $this->parse($line);
            if ($entry === null || ($userId !== null && $entry->userId !== $userId)) {
                continue;
            }

            $entries[] = $entry;
            if (count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
    }

    /** @return list<string> */
    private function tailLines(): array
    {
        $path = $this->file->path();
        $size = is_file($path) ? filesize($path) : false;
        $handle = $size === false || $size === 0 ? false : @fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $start = max(0, $size - self::TAIL_BYTES);
        fseek($handle, $start);
        $content = (string)stream_get_contents($handle);
        fclose($handle);

        $lines = explode("\n", $content);
        if ($start > 0) {
            array_shift($lines); // starts in the middle of a line
        }

        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }

    private function parse(string $line): ?AuditEntry
    {
        if (preg_match(self::LINE, $line, $match) !== 1) {
            return null;
        }

        $context = json_decode($match['context'], true);
        if (!is_array($context) || !isset($context['user_id'])) {
            return null;
        }

        $origin = (string)($context['ip'] ?? '');
        if (isset($context['forwarded_for'])) {
            $origin .= ' (' . $context['forwarded_for'] . ')';
        }

        return new AuditEntry(
            $match['time'],
            $match['event'],
            (string)$context['user_id'],
            (string)($context['actor_id'] ?? $context['user_id']),
            $origin,
        );
    }
}
