<?php

declare(strict_types=1);

namespace Magephi\Command\Environment;

use Magephi\Exception\EnvironmentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to build containers for the environment.
 */
class BuildCommand extends AbstractEnvironmentCommand
{
    protected string $command = 'build';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Build docker containers, equivalent to <fg=yellow>make build</>')
            ->setHelp(
                'This command allows you to build container for your Magento 2 environment.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->interactive->section('Building environment');

        try {
            $this->manager->build();
        } catch (EnvironmentException $e) {
            $this->interactive->newLine(2);
            $this->interactive->error($e->getMessage());

            return self::FAILURE;
        }

        $this->interactive->newLine(2);
        $this->interactive->success('Containers have been built.');

        return self::SUCCESS;
    }
}
