<?php

namespace Magephi\Command\Environment;

use Magephi\Command\AbstractCommand;
use Magephi\Component\DockerCompose;
use Magephi\Component\Process;
use Magephi\Component\ProcessFactory;
use Magephi\Helper\Make;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to build containers for the environment.
 */
class BuildCommand extends AbstractEnvironmentCommand
{
    protected string $command = 'build';

    private Make $make;

    public function __construct(
        ProcessFactory $processFactory,
        DockerCompose $dockerCompose,
        Make $make,
        string $name = null
    ) {
        parent::__construct($processFactory, $dockerCompose, $name);
        $this->make = $make;
    }

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

        $process = $this->make->build();

        $this->interactive->newLine(2);

        if (!$process->getProcess()->isSuccessful()) {
            if ($process->getExitCode() === Process::CODE_TIMEOUT) {
                $this->interactive->error('Build timeout, use the option --no-timeout or run directly `make build` to build the environment.');
            } else {
                $this->interactive->error($process->getProcess()->getErrorOutput());
                $this->interactive->note(
                    [
                        "Ensure you're not using a deleted branch for package emakinafr/docker-magento2.",
                        'This issue may came from a missing package in the PHP dockerfile after a version upgrade.',
                    ]
                );
            }

            return AbstractCommand::CODE_ERROR;
        }

        $this->interactive->success('Containers have been built.');

        return AbstractCommand::CODE_SUCCESS;
    }
}
