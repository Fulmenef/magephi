<?php

namespace Magphi\Component;

use Magphi\Entity\Environment;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class Mutagen
{
    /** @var ProcessFactory */
    private $processFactory;
    /** @var Environment */
    private $environment;

    public function __construct(ProcessFactory $processFactory)
    {
        $this->processFactory = $processFactory;
        $this->environment = new Environment();
        $this->environment->autoLocate();
    }

    /**
     * @return Process
     */
    public function createSession(): Process
    {
        $command = [
            'mutagen',
            'create',
            '--default-owner-beta=id:1000',
            '--default-group-beta=id:1000',
            '--sync-mode=two-way-resolved',
            '--ignore-vcs',
            '--symlink-mode=posix-raw',
            "--label=name={$this->environment->getDockerRequiredVariables()['COMPOSE_PROJECT_NAME']}",
            getcwd(),
            $this->environment->getDockerRequiredVariables()['COMPOSE_PROJECT_NAME']
                ? "docker://magento2_{$this->environment->__get('currentDir')}_synchro/var/www/html/"
                : '',
        ];

        return $this->processFactory->runProcess($command, 60);
    }

    public function resumeSession(): Process
    {
        return $this->processFactory->runProcess(
            [
                'mutagen',
                'resume',
                "--label-selector=name={$this->environment->getDockerRequiredVariables()['COMPOSE_PROJECT_NAME']}",
            ]
        );
    }

    /**
     * @return bool
     */
    public function isExistingSession(): bool
    {
        $process = $this->processFactory->runProcess(
            [
                'mutagen',
                'list',
                "--label-selector=name={$this->environment->getDockerRequiredVariables()['COMPOSE_PROJECT_NAME']}",
            ]
        );

        return strpos($process->getProcess()->getOutput(), 'No sessions found') === false;
    }

    public function isSynced(): bool
    {
        $process = $this->processFactory->runProcess(
            [
                'mutagen',
                'list',
                "--label-selector=name={$this->environment->getDockerRequiredVariables()['COMPOSE_PROJECT_NAME']}",
            ]
        );

        return stripos($process->getProcess()->getOutput(), 'Watching for changes') !== false;
    }

    public function isPaused(): bool
    {
        $process = $this->processFactory->runProcess(
            [
                'mutagen',
                'list',
                "--label-selector=name={$this->environment->getDockerRequiredVariables()['COMPOSE_PROJECT_NAME']}",
            ]
        );

        return stripos($process->getProcess()->getOutput(), '[Paused]') !== false;
    }

    public function monitorUntilSynced(): bool
    {
        try {
            $process = $this->processFactory->createProcess(
                [
                    'mutagen',
                    'monitor',
                    "--label-selector=name=={$this->environment->getDockerRequiredVariables()['COMPOSE_PROJECT_NAME']}",
                ],
                300
            );
            $re = '/Status: (.*)$/i';
            $process->start();
            $process->getProcess()->waitUntil(
                function (string $type, string $buffer) use ($re) {
                    preg_match($re, $buffer, $match);
                    return rtrim($match[1]) === 'Watching for changes';
                }
            );

            return true;
        } catch (ProcessTimedOutException $e) {
            return false;
        }
    }
}
