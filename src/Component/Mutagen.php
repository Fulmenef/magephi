<?php

declare(strict_types=1);

namespace Magephi\Component;

use Magephi\Entity\Environment;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class Mutagen
{
    private ProcessFactory $processFactory;

    private Environment $environment;

    private OutputInterface $outputInterface;

    public function __construct(ProcessFactory $processFactory, Environment $environment)
    {
        $this->processFactory = $processFactory;
        $this->environment = $environment;
        $this->outputInterface = new ConsoleOutput();
    }

    /**
     * Create Mutagen session for the project.
     *
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
            getcwd() ?: '',
            $this->environment->getDockerRequiredVariables()['COMPOSE_PROJECT_NAME']
                ? "docker://magento2_{$this->environment->__get('currentDir')}_synchro/var/www/html/"
                : '',
        ];

        return $this->processFactory->runProcess($command, 60);
    }

    /**
     * Try to resume the Mutagen session. Obviously, it must have been initialized first.
     *
     * @return Process
     */
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
     * Check if the mutagen session for the project exists. Return true if it does.
     *
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

    /**
     * Check if the file synchronization is done.
     *
     * @return bool
     */
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

    /**
     * Check if the mutagen session is paused.
     *
     * @return bool
     */
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

    /**
     * Display a progress bar until the file synchronization is done.
     *
     * @return bool
     */
    public function monitorUntilSynced(): bool
    {
        $process = $this->processFactory->createProcess(
            [
                'mutagen',
                'monitor',
                "--label-selector=name=={$this->environment->getDockerRequiredVariables()['COMPOSE_PROJECT_NAME']}",
            ],
            300
        );
        $progressBar = new ProgressBar($this->outputInterface, 100);
        $reStatus = '/Status: (.*)$/i';
        $reProgress = '/Staging files on beta: (\d+)%/i';
        $process->start();
        $progressBar->start();
        $process->getProcess()->waitUntil(
            function (string $type, string $buffer) use ($reStatus, $reProgress, $progressBar) {
                preg_match($reStatus, $buffer, $statusMatch);
                if (isset($statusMatch[1])) {
                    preg_match($reProgress, $statusMatch[1], $progressMatch);
                    if (!empty($progressMatch)) {
                        $progressBar->setProgress((int) $progressMatch[1]);
                    }

                    return rtrim($statusMatch[1]) === 'Watching for changes';
                }

                return false;
            }
        );
        $progressBar->finish();

        return $process->getExitCode() !== Process::CODE_TIMEOUT;
    }
}
