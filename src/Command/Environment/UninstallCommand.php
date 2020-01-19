<?php

namespace Magephi\Command\Environment;

use Magephi\Command\AbstractCommand;
use Magephi\Component\DockerCompose;
use Magephi\Component\ProcessFactory;
use Magephi\Helper\Make;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to uninstall the environment. It simply remove volumes and destroy containers and the mutagen session.
 */
class UninstallCommand extends AbstractEnvironmentCommand
{
    protected string $command = 'uninstall';

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

    public function getPrerequisites(): array
    {
        $prerequisites = parent::getPrerequisites();
        $prerequisites['binary'] = array_merge($prerequisites['binary'], ['Mutagen']);

        return $prerequisites;
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Uninstall the Magento2 project in the current directory.')
            ->setHelp('This command allows you to uninstall the Magento 2 project in the current directory.');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->interactive->confirm('Are you sure you want to uninstall this project ?', false)) {
            $process = $this->make->purge();
            $this->interactive->newLine(2);

            if (!$process->getProcess()->isSuccessful()) {
                $this->interactive->error(
                    [
                        "Environment couldn't be uninstall: ",
                        $process->getProcess()->getErrorOutput(),
                    ]
                );

                return AbstractCommand::CODE_ERROR;
            }

            $this->interactive->success('This project has been successfully uninstalled.');
        }

        return AbstractCommand::CODE_SUCCESS;
    }
}
