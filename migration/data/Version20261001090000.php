<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Two-factor authentication: enrolments, recovery codes, failed attempts';
    }

    public function up(Schema $schema): void
    {
        $this->connection->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

        if (!$schema->hasTable('oxid2fa_twofactor')) {
            $this->addSql("CREATE TABLE `oxid2fa_twofactor` (
                `id` char(32) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
                `user_id` char(32) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL
                    COMMENT 'oxuser.OXID',
                `secret_encrypted` text CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
                `enabled_at` datetime NULL DEFAULT NULL
                    COMMENT 'UTC, NULL while the setup is not confirmed',
                `last_used_step` bigint NULL DEFAULT NULL
                    COMMENT 'Last accepted TOTP time step, replay protection',
                `created_at` datetime NOT NULL,
                `updated_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        }

        if (!$schema->hasTable('oxid2fa_twofactor_recovery_code')) {
            $this->addSql("CREATE TABLE `oxid2fa_twofactor_recovery_code` (
                `id` char(32) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
                `two_factor_id` char(32) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
                `code_hash` char(64) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
                `used_at` datetime NULL DEFAULT NULL,
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `enrollment_code` (`two_factor_id`, `code_hash`),
                CONSTRAINT `oxid2fa_twofactor_recovery_code_enrollment` FOREIGN KEY (`two_factor_id`)
                    REFERENCES `oxid2fa_twofactor` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        }

        if (!$schema->hasTable('oxid2fa_required')) {
            $this->addSql("CREATE TABLE `oxid2fa_required` (
                `user_id` char(32) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        }

        if (!$schema->hasTable('oxid2fa_twofactor_attempt')) {
            $this->addSql("CREATE TABLE `oxid2fa_twofactor_attempt` (
                `user_id` char(32) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
                `failures` int NOT NULL DEFAULT 0
                    COMMENT 'Attempts in the current window, counted before the code is checked',
                `updated_at` datetime NOT NULL,
                PRIMARY KEY (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS `oxid2fa_required`');
        $this->addSql('DROP TABLE IF EXISTS `oxid2fa_twofactor_attempt`');
        $this->addSql('DROP TABLE IF EXISTS `oxid2fa_twofactor_recovery_code`');
        $this->addSql('DROP TABLE IF EXISTS `oxid2fa_twofactor`');
    }
}
