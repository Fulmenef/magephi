<?php

namespace Magephi\Helper;

use Magephi\Component\DockerCompose;
use Magephi\Component\Mutagen;
use Magephi\Component\Process;
use Magephi\Component\ProcessFactory;
use Magephi\Entity\Environment;
use Magephi\Entity\System;
use Magephi\Exception\EnvironmentException;
use Magephi\Exception\ProcessException;
use Symfony\Component\Console\Output\OutputInterface;

class Installation
{
    /** @var ProcessFactory */
    private $processFactory;

    /** @var DockerCompose */
    private $dockerCompose;

    /** @var Mutagen */
    private $mutagen;

    /** @var Environment */
    private $environment;

    /** @var OutputInterface */
    private $outputInterface;

    /** @var System */
    private $system;

    public function __construct(
        DockerCompose $dockerCompose,
        ProcessFactory $processFactory,
        Mutagen $mutagen,
        Environment $environment,
        System $system
    ) {
        $this->dockerCompose = $dockerCompose;
        $this->processFactory = $processFactory;
        $this->mutagen = $mutagen;
        $this->environment = $environment;
        $this->system = $system;
    }

    public function setOutputInterface(OutputInterface $outputInterface): void
    {
        $this->outputInterface = $outputInterface;
    }

    /**
     * Import a database dump. Display a progress bar to visually follow the process.
     *
     * @param string $database
     * @param string $filename
     *
     * @throws EnvironmentException
     *
     * @return Process
     */
    public function databaseImport(string $database, string $filename): Process
    {
        if (!$this->dockerCompose->isContainerUp('mysql')) {
            throw new EnvironmentException('Mysql container is not up');
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        switch ($ext) {
            case 'zip':
                $command = ['bsdtar', '-xOf-'];

                break;
            case 'gz':
            case 'gzip':
                $command = ['gunzip', '-cd'];

                break;
            case 'sql':
            default:
                $command = [];

                break;
        }

        $readCommand = ['pv', '-ptefab'];
        if (!$this->system->getBinaryPrerequisites()['Pipe Viewer']['status']) {
            $this->outputInterface->writeln('<comment>Pipe Viewer is not installed, it is necessary to have a progress bar.</comment>');
            $readCommand = ['cat'];
        }

        $command = array_merge(
            array_merge($readCommand, [$filename, '|']),
            !empty($command) ? array_merge($command, ['|']) : $command,
            ['mysql', '-h', '127.0.0.1', '-u', 'root', '-D', $database]
        );

        $this->outputInterface->writeln('');
        $this->outputInterface->writeln('<fg=yellow>Import started');
        $this->outputInterface->writeln('<fg=yellow>--------------');
        $this->outputInterface->writeln('');

        return $this->processFactory->runProcessWithOutput(
            $command,
            3600,
            null,
            true
        );
    }

    /**
     * Update URLs in the database with the configured server name.
     *
     * @param string $database
     *
     * @throws EnvironmentException
     *
     * @return Process
     */
    public function updateUrls(string $database)
    {
        if (!$this->dockerCompose->isContainerUp('mysql')) {
            throw new EnvironmentException('Mysql container is not up');
        }

        $serverName = $this->environment->getServerName(true);

        return $this->processFactory->runProcess(
            [
                'mysql',
                '-h',
                '127.0.0.1',
                '-u',
                'root',
                $database,
                '-e',
                '"UPDATE core_config_data SET value=\"' . $serverName . '/\" WHERE path LIKE \"web%base_url\""',
            ],
            30,
            [],
            true
        );
    }

    /**
     * Run the `make start` command with a progress bar.
     *
     * @param bool $install
     *
     * @return Process
     */
    public function startMake(bool $install = false): Process
    {
        return $this->processFactory->runProcessWithProgressBar(
            ['make', 'start'],
            60,
            function (/* @noinspection PhpUnusedParameterInspection */ $type, $buffer) {
                return (strpos($buffer, 'Creating') !== false
                        && (
                            strpos($buffer, 'network')
                            || strpos($buffer, 'volume')
                            || strpos($buffer, 'done')
                        ))
                    || (strpos($buffer, 'Starting') && strpos($buffer, 'done'));
            },
            $this->outputInterface,
            $install ? $this->environment->getContainers() + $this->environment->getVolumes()
                + 2 : $this->environment->getContainers() + 1
        );
    }

    /**
     * Run the `make build` command with a progress bar.
     *
     * @return Process
     */
    public function buildMake(): Process
    {
        $process = $this->processFactory->runProcessWithProgressBar(
            ['make', 'build'],
            600,
            function (/* @noinspection PhpUnusedParameterInspection */ $type, $buffer) {
                return strpos($buffer, 'skipping') || strpos($buffer, 'tagged');
            },
            $this->outputInterface,
            $this->environment->getContainers()
        );

        return $process;
    }

    /**
     * Start or resume the mutagen session.
     */
    public function startMutagen(): bool
    {
        if (!$this->dockerCompose->isContainerUp('synchro')) {
            throw new ProcessException('Synchro container is not started');
        }
        if ($this->mutagen->isExistingSession()) {
            if ($this->mutagen->isPaused()) {
                $this->mutagen->resumeSession();
            }
        } else {
            $process = $this->mutagen->createSession();
            if (!$process->getProcess()->isSuccessful()) {
                throw new ProcessException('Mutagen session could not be created');
            }
        }

        return true;
    }
}
