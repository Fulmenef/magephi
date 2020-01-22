<?php

namespace Magephi\Command\Environment;

use Exception;
use Magephi\Command\AbstractCommand;
use Magephi\Component\DockerCompose;
use Magephi\Component\Mutagen;
use Magephi\Component\Process;
use Magephi\Component\ProcessFactory;
use Magephi\Helper\Make;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to start the environment. The install command must have been executed before.
 */
class StartCommand extends AbstractEnvironmentCommand
{
    protected string $command = 'start';

    private Make $make;

    private Mutagen $mutagen;

    public function __construct(
        ProcessFactory $processFactory,
        DockerCompose $dockerCompose,
        Make $make,
        Mutagen $mutagen,
        string $name = null
    ) {
        parent::__construct($processFactory, $dockerCompose, $name);
        $this->make = $make;
        $this->mutagen = $mutagen;
    }

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
            $process = $this->make->start();
            if (!$process->getProcess()->isSuccessful() && $process->getExitCode() !== Process::CODE_TIMEOUT) {
                throw new Exception($process->getProcess()->getErrorOutput());
            }
            if ($process->getExitCode() === Process::CODE_TIMEOUT) {
                $this->make->startMutagen();
                $this->interactive->newLine();
                $this->interactive->text('Containers are up.');
                $this->interactive->section('File synchronization');
                $synced = $this->mutagen->monitorUntilSynced();
                if (!$synced) {
                    throw new Exception(
                        'Something happened during the sync, check the situation with <fg=yellow>mutagen monitor</>.'
                    );
                }
            }

            $this->interactive->newLine(2);
            $this->interactive->success('Environment started.');

            return AbstractCommand::CODE_SUCCESS;
        } catch (Exception $e) {
            $this->interactive->error(
                [
                    "Environment couldn't been started:",
                    $e->getMessage(),
                ]
            );

            return AbstractCommand::CODE_ERROR;
        }
    }
}
