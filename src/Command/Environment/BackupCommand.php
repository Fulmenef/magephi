<?php

namespace Magephi\Command\Environment;

use Magephi\Command\AbstractCommand;
use Magephi\Component\DockerCompose;
use Magephi\Component\ProcessFactory;
use Magephi\Entity\Environment;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * Command to backup the database with the environment files.
 */
class BackupCommand extends AbstractEnvironmentCommand
{
    public const BACKUP_FILE = 'backup';

    public const ARGUMENT_FILE = 'file';

    public const ARGUMENT_COMPRESSION = 'compressed';

    public const MYSQL_BACKUP_FILE = 'mysql_backup.tar';

    public const EXTENSION = '.tar';

    public const COMPRESSED_EXTENSION = '.gz';

    private const DEFAULT_TIMEOUT = 300;

    protected string $command = 'backup';

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
            ->addArgument(self::ARGUMENT_FILE, InputArgument::OPTIONAL, 'Filename for the backup', self::BACKUP_FILE)
            ->addOption(
                self::ARGUMENT_COMPRESSION,
                'c',
                InputOption::VALUE_NONE,
                'Compress the output backup. It will take twice the regular time but will be much more lighter'
            )
            ->setDescription('Extract MySql data, docker .env and env.php in an archive.')
            ->setHelp('This command allows you backup your environment');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $filename */
        $filename = $input->getArgument(self::ARGUMENT_FILE);
        $filename .= self::EXTENSION;

        $timeout = self::DEFAULT_TIMEOUT;
        if ($compression = $input->hasOption(self::ARGUMENT_COMPRESSION)) {
            $filename .= self::COMPRESSED_EXTENSION;
            $timeout *= 3;
        }

        $this->interactive->section('Backup generation');

        try {
            if (!$output->isVerbose()) {
                $progressBar = new ProgressBar($output, 2);
                $progressBar->display();
            }

            $command = [
                'docker run --rm',
                '--volumes-from $(docker-compose ps -q mysql)',
                '--volume $(pwd):/backup',
                'busybox sh -c "tar cvf /backup/' . self::MYSQL_BACKUP_FILE
                . ' /var/lib/mysql"',
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

            $tarParemeters = 'c' . ($compression ? 'z' : '') . 'vf';

            $command = [
                'docker run --rm',
                '--volume $(pwd):/backup',
                'busybox sh -c "tar ' . $tarParemeters . ' /backup/' . $filename . ' /backup/'
                    . self::MYSQL_BACKUP_FILE . ' /backup/'
                    . $this->environment->__get('magentoEnv') . ' /backup/' . $this->environment->__get('localEnv')
                    . '"',
            ];
            $this->processFactory->runProcess(
                $command,
                $timeout,
                $this->environment->getDockerRequiredVariables(),
                true
            );
            unlink(self::MYSQL_BACKUP_FILE);

            if (isset($progressBar)) {
                $progressBar->finish();
            }

            $this->interactive->newLine(2);
            $this->interactive->success(
                'Your backup has been successfully generated. Path is ' . posix_getcwd() . '/' . $filename
            );
        } catch (ProcessTimedOutException $e) {
            $this->interactive->newLine(2);
            $this->interactive->error(
                'Seems like the backup process exceeded the default timeout of ' . ($timeout / 60)
                . ' minutes, please run again the command with the option --no-timeout'
            );

            return AbstractCommand::CODE_ERROR;
        }

        return AbstractCommand::CODE_SUCCESS;
    }
}
