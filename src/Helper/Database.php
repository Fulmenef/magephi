<?php

declare(strict_types=1);

namespace Magephi\Helper;

use Magephi\Component\Process;
use Magephi\Component\ProcessFactory;
use Magephi\Entity\Environment\EnvironmentInterface;
use Magephi\Entity\System;
use Magephi\Exception\EnvironmentException;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class Database
{
    private ProcessFactory $processFactory;

    private EnvironmentInterface $environment;

    private System $system;

    private SymfonyStyle $output;

    public function __construct(
        ProcessFactory $processFactory,
        System $system
    ) {
        $this->processFactory = $processFactory;
        $this->system = $system;
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
            $this->output->comment('Pipe Viewer is not installed, it is necessary to have a progress bar.');
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
                'docker-compose',
                'exec',
                '-T',
                '--env',
                'MYSQL_PWD=' . $password,
                'mysql',
                'mysql',
                '-h',
                '127.0.0.1',
                '-u',
                $username,
                '-D',
                $database,
            ]
        );

        $this->output->section('Import started');

        return $this->processFactory->runProcessWithOutput(
            $command,
            3600,
            $this->environment->getDockerRequiredVariables(),
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
        $serverName = $this->environment->getServerName(true);
        $username = $this->environment->getEnvData('mysql_user') ?: 'root';
        $password = $username === 'root' ? $this->environment->getEnvData(
            'mysql_root_password'
        ) : $this->environment->getEnvData('mysql_password');

        return $this->processFactory->runProcess(
            [
                'docker-compose',
                'exec',
                '-T',
                '--env',
                'MYSQL_PWD=' . $password,
                'mysql',
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
            $this->environment->getDockerRequiredVariables(),
            true
        );
    }

    /**
     * @param EnvironmentInterface $environment
     *
     * @return Database
     */
    public function setEnvironment(EnvironmentInterface $environment): self
    {
        $this->environment = $environment;

        return $this;
    }

    /**
     * @param SymfonyStyle $output
     *
     * @return Database
     */
    public function setOutput(SymfonyStyle $output): self
    {
        $this->output = $output;

        return $this;
    }
}
