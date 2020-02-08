<?php

namespace Magephi\Command\Environment;

use InvalidArgumentException;
use Magephi\Command\AbstractCommand;
use Magephi\Component\DockerCompose;
use Magephi\Component\ProcessFactory;
use Magephi\Entity\Environment;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * Command to restore a backup made by the Backup command.
 *
 * @see BackupCommand
 */
class RestoreCommand extends AbstractEnvironmentCommand
{
    private const DEFAULT_TIMEOUT = 300;

    protected string $command = 'restore';

    private Environment $environment;

    public function __construct(
        ProcessFactory $processFactory,
        DockerCompose $dockerCompose,
        Environment $environment,
        string $name = null
    ) {
        parent::__construct($processFactory, $dockerCompose, $name);
        $this->environment = $environment;
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->addArgument(BackupCommand::ARGUMENT_FILE, InputArgument::REQUIRED, 'Path to the of the backup')
            ->setDescription('Extract MySql data and environment configuration from a backup in the current project')
            ->setHelp('This command allows you to restore a backup of an environment');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $path */
        $path = $input->getArgument(BackupCommand::ARGUMENT_FILE);

        if (!is_file($path)) {
            throw new InvalidArgumentException($path . ' is not a file.');
        }

        $compressed = false;
        $timeout = self::DEFAULT_TIMEOUT;
        $extension = pathinfo($path)['extension'];
        if ('.' . $extension === BackupCommand::COMPRESSED_EXTENSION) {
            $compressed = true;
            $timeout = self::DEFAULT_TIMEOUT * 2;
        }
        $filename = basename($path);
        $magentoEnv = $this->environment->__get('magentoEnv');
        $dockerEnv = $this->environment->__get('localEnv');

        $this->interactive->section('Backup restoration');
        $tarParemeters = 'x' . ($compressed ? 'z' : '') . 'vf';

        try {
            if (!$output->isVerbose()) {
                $progressBar = new ProgressBar($output, 2);
                $progressBar->display();
            }
            $command = [
                'docker run --rm',
                '--volumes-from $(docker-compose ps -q mysql)',
                '--volume $(pwd):/project',
                '--volume ' . $path . ':/backup/' . $filename,
                'busybox sh -c " \
                tar ' . $tarParemeters . ' /backup/' . $filename . ' && \ 
                tar xvf /backup/' . BackupCommand::MYSQL_BACKUP_FILE . ' var/lib/mysql && \
                mv /backup/' . $magentoEnv . ' /project/' . $magentoEnv . '&& \
                mv /backup/' . $dockerEnv . ' /project/' . $dockerEnv . ' \
                "',
            ];
            $this->processFactory->runProcess(
                $command,
                $timeout,
                $this->environment->getDockerRequiredVariables(),
                true
            );

            if (isset($progressBar)) {
                $progressBar->advance();
            }

            $this->dockerCompose->restartContainer('mysql');

            if (isset($progressBar)) {
                $progressBar->advance();
            }
        } catch (ProcessTimedOutException $e) {
            $this->interactive->newLine(2);
            $this->interactive->error(
                'Seems like the restore process exceeded the default timeout of ' . (self::DEFAULT_TIMEOUT / 60)
                . ' minutes, please run again the command with the option --no-timeout'
            );

            return AbstractCommand::CODE_ERROR;
        }

        $this->interactive->newLine(2);
        $this->interactive->success('Restore is complete');

        return AbstractCommand::CODE_SUCCESS;
    }
}
