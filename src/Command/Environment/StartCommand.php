<?php

declare(strict_types=1);

namespace Magephi\Command\Environment;

use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to start the environment. The install command must have been executed before.
 */
class StartCommand extends AbstractEnvironmentCommand
{
    protected string $command = 'start';

    public function getPrerequisites(): array
    {
        $prerequisites = parent::getPrerequisites();
        $prerequisites['binary'] = array_merge($prerequisites['binary'], ['Mutagen']);
        $prerequisites['service'] = array_merge($prerequisites['service'], ['Mutagen']);

        return $prerequisites;
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Start environment, equivalent to <fg=yellow>make start</>')
            ->setHelp(
                'This command allows you to start your Magento 2 environment. It must have been installed before.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->interactive->section('Starting environment');

        try {
            $this->manager->start();
        } catch (Exception $e) {
            $this->interactive->newLine(2);
            $this->interactive->error(
                [
                    "Environment couldn't be started:",
                    $e->getMessage(),
                ]
            );

            return self::FAILURE;
        }

        $this->interactive->newLine(2);
        $this->interactive->success('Environment started.');

        return self::SUCCESS;
    }
}
