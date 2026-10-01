<?php

/**
 * Copyright (c) 2026 dazi-web
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DaziWeb\Oxid2Fa\Command;

use OxidEsales\EshopCommunity\Internal\Framework\Database\ConnectionProviderInterface;
use DaziWeb\Oxid2Fa\Application\EnrollmentService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResetTwoFactorCommand extends Command
{
    private const ACTOR = 'cli';

    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly ConnectionProviderInterface $connectionProvider,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('oxid2fa:reset')
            ->setDescription('Removes the two-factor authentication of an administrator.')
            ->setHelp(
                'Deletes secret and recovery codes. With mandatory 2FA the user sets it up again at the next login.'
            )
            ->addArgument('user', InputArgument::REQUIRED, 'Login name (e-mail) of the administrator');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userId = $this->findAdminId((string)$input->getArgument('user'));
        if ($userId === null) {
            $output->writeln('<error>No administrator with this login name was found.</error>');

            return Command::FAILURE;
        }

        $this->enrollments->reset($userId, self::ACTOR);
        $output->writeln('Two-factor authentication was reset.');

        return Command::SUCCESS;
    }

    private function findAdminId(string $loginName): ?string
    {
        $userId = $this->connectionProvider->get()->fetchOne(
            "SELECT oxid FROM oxuser WHERE oxusername = :name AND oxrights != 'user'",
            ['name' => $loginName]
        );

        return $userId === false ? null : (string)$userId;
    }
}
