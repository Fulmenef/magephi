<?php

namespace Magephi\Helper;

use Magephi\Component\DockerCompose;
use Magephi\Component\Process;
use Magephi\Component\ProcessFactory;
use Magephi\Entity\Environment;
use Magephi\Entity\System;
use Magephi\Exception\EnvironmentException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class Database
{
    private ProcessFactory $processFactory;

    private DockerCompose $dockerCompose;

    private Environment $environment;

    private System $system;

    private SymfonyStyle $interactive;

    private ConsoleOutput $outputInterface;

    public function __construct(
        DockerCompose $dockerCompose,
        ProcessFactory $processFactory,
        Environment $environment,
        System $system
    ) {
        $this->dockerCompose = $dockerCompose;
        $this->processFactory = $processFactory;
        $this->environment = $environment;
        $this->system = $system;
        $this->outputInterface = new ConsoleOutput();
        $this->interactive = new SymfonyStyle(new ArgvInput(), $this->outputInterface);
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
    public function import(string $database, string $filename): Process
    {
        if (!$this->dockerCompose->isContainerUp('mysql')) {
            throw new EnvironmentException('Mysql container is not started');
        }

        if (!file_exists($filename)) {
            throw new FileException(sprintf('File %s does not exist', $filename));
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
            $this->interactive->comment('Pipe Viewer is not installed, it is necessary to have a progress bar.');
            $readCommand = ['cat'];
        }

        $username = $this->environment->getEnvData('mysql_user') ?: 'root';
        $password = $username === 'root' ? $this->environment->getEnvData(
            'mysql_root_password'
        ) : $this->environment->getEnvData('mysql_password');

        $command = array_merge(
            array_merge($readCommand, [$filename, '|']),
            !empty($command) ? array_merge($command, ['|']) : $command,
            [
                'mysql',
                '-h',
                '127.0.0.1',
                '-u',
                $username,
                '-D',
                $database,
            ]
        );

        $this->interactive->section('Import started');

        return $this->processFactory->runProcessWithOutput(
            $command,
            3600,
            ['MYSQL_PWD' => $password],
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
            throw new EnvironmentException('Mysql container is not started');
        }

        $serverName = $this->environment->getServerName(true);
        $username = $this->environment->getEnvData('mysql_user') ?: 'root';
        $password = $username === 'root' ? $this->environment->getEnvData(
            'mysql_root_password'
        ) : $this->environment->getEnvData('mysql_password');

        return $this->processFactory->runProcess(
            [
                'mysql',
                '-h',
                '127.0.0.1',
                '-u',
                $username,
                $database,
                '-e',
                '"UPDATE core_config_data SET value=\"' . $serverName . '/\" WHERE path LIKE \"web%base_url\""',
            ],
            30,
            ['MYSQL_PWD' => $password],
            true
        );
    }
}
