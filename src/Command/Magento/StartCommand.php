<?php

namespace Magephi\Command\Magento;

use Magephi\Component\DockerCompose;
use Magephi\Component\Mutagen;
use Magephi\Component\Process;
use Magephi\Component\ProcessFactory;
use Magephi\Helper\Installation;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * Command to start the environment. The install command must have been executed before.
 */
class StartCommand extends AbstractMagentoCommand
{
    protected $command = 'start';

    /** @var Installation */
    private $installation;
    private $mutagen;

    public function __construct(
        ProcessFactory $processFactory,
        DockerCompose $dockerCompose,
        Installation $installation,
        Mutagen $mutagen,
        string $name = null
    ) {
        parent::__construct($processFactory, $dockerCompose, $name);
        $this->installation = $installation;
        $this->mutagen      = $mutagen;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->installation->setOutputInterface($output);
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Start environment, equivalent to <fg=yellow>make start</>')
            ->setHelp(
                'This command allows you to start your Magento 2 environment. It must have been installed before.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $this->interactive->section('Starting environment');

        try {
            try {
                $process = $this->installation->startMake();
                if (!$process->isSuccessful()) {
                    throw new \Exception($process->getErrorOutput());
                }
            } catch (ProcessTimedOutException $e) {
                /** @var Process $startProcess */
                $startProcess = $e->getProcess();
                $this->installation->startMutagen();
                $progressBar = $startProcess->getProgressBar();
                if (!$progressBar instanceof ProgressBar) {
                    throw new \Exception('The progress bar is not defined.');
                }
                $progressBar->setMaxSteps($progressBar->getProgress());
                $progressBar->finish();
                $this->interactive->newLine();
                $this->interactive->text('Containers are up.');
                $this->interactive->section('File synchronization');
                $synced = $this->mutagen->monitorUntilSynced($output);
                if (!$synced) {
                    throw new \Exception(
                        'Something happened during the sync, check the situation with <fg=yellow>mutagen monitor</>.'
                    );
                }
            }

            $this->interactive->newLine(2);
            $this->interactive->success('Environment started');

            return null;
        } catch (\Exception $e) {
            $this->interactive->error(
                [
                    "Environment couldn't been started:",
                    $e->getMessage(),
                ]
            );

            return 1;
        }
    }
}
