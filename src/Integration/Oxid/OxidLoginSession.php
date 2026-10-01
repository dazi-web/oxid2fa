<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Integration\Oxid;

use DateTimeImmutable;
use OxidEsales\Eshop\Application\Model\User;
use OxidEsales\Eshop\Core\Registry;
use DaziWeb\Oxid2Fa\Application\LoginSession;
use DaziWeb\Oxid2Fa\Domain\AuthenticatedLogin;
use DaziWeb\Oxid2Fa\Domain\PendingLogin;
use DaziWeb\Oxid2Fa\Domain\PendingStep;

/**
 * Admin back end: User::login() stores the user id in the session variable "auth" and a password
 * fingerprint in "login-token". Both Utils::checkAccessRights() (used by AdminController and oxajax.php)
 * and User::loadAdminUser() read only these two.
 */
final class OxidLoginSession implements LoginSession
{
    private const USER = 'auth';
    private const TOKEN = 'login-token';
    private const PENDING = 'oxid2fa_twofactor_pending';

    public function withhold(): ?AuthenticatedLogin
    {
        $session = Registry::getSession();
        $userId = $session->getVariable(self::USER);
        if (!is_string($userId) || $userId === '') {
            return null;
        }

        $token = (string)$session->getVariable(self::TOKEN);
        $session->deleteVariable(self::USER);
        $session->deleteVariable(self::TOKEN);

        return new AuthenticatedLogin($userId, $token);
    }

    public function restore(AuthenticatedLogin $login): void
    {
        $session = Registry::getSession();
        $session->setVariable(self::USER, $login->userId);
        $session->setVariable(self::TOKEN, $login->loginToken);
    }

    public function rotateSessionId(): void
    {
        Registry::getSession()->regenerateSessionId();
    }

    public function storePending(PendingLogin $pending): void
    {
        Registry::getSession()->setVariable(self::PENDING, [
            'user' => $pending->login->userId,
            'token' => $pending->login->loginToken,
            'step' => $pending->step->value,
            'started' => $pending->startedAt->getTimestamp(),
        ]);
    }

    public function pending(): ?PendingLogin
    {
        $data = Registry::getSession()->getVariable(self::PENDING);
        if (!is_array($data)) {
            return null;
        }

        return new PendingLogin(
            new AuthenticatedLogin((string)$data['user'], (string)$data['token']),
            PendingStep::from((string)$data['step']),
            (new DateTimeImmutable())->setTimestamp((int)$data['started']),
        );
    }

    public function clearPending(): void
    {
        Registry::getSession()->deleteVariable(self::PENDING);
    }

    public function abandon(): void
    {
        $this->clearPending();
        oxNew(User::class)->logout();
        Registry::getSession()->destroy();
    }
}
