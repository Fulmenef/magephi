<?php

namespace Magephi\Command\Environment;

use Magephi\Command\AbstractCommand;
use Magephi\Component\DockerCompose;
use Magephi\Component\ProcessFactory;
use Magephi\Helper\Make;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to stop the environment. The install command must have been executed before.
 */
class StopCommand extends AbstractEnvironmentCommand
{
    protected string $command = 'stop';

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
            ->setDescription('Stop environment, equivalent to <fg=yellow>make stop</>')
            ->setHelp(
                'This command allows you to stop your Magento 2 environment. It must have been installed before.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->interactive->section('Stopping environment');

        $process = $this->make->stop();

        $this->interactive->newLine(2);

        if (!$process->getProcess()->isSuccessful()) {
            $this->interactive->error(
                [
                    "Environment couldn't be stopped: ",
                    $process->getProcess()->getErrorOutput(),
                ]
            );

            return AbstractCommand::CODE_ERROR;
        }

        $this->interactive->success('Environment stopped.');

        return AbstractCommand::CODE_SUCCESS;
    }
}
