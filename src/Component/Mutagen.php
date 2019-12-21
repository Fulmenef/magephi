<?php

namespace Magephi\Component;

use Magephi\Entity\Environment;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
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
    }

    /**
     * Create Mutagen session for the project.
     *
     * @return ProcessInterface
     */
    public function createSession(): ProcessInterface
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

    /**
     * Try to resume the Mutagen session. Obviously, it must have been initialized first.
     *
     * @return ProcessInterface
     */
    public function resumeSession(): ProcessInterface
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

        return strpos($process->getOutput(), 'No sessions found') === false;
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

        return stripos($process->getOutput(), 'Watching for changes') !== false;
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

        return stripos($process->getOutput(), '[Paused]') !== false;
    }

    /**
     * Display a progress bar until the file synchronization is done.
     *
     * @param OutputInterface $output
     *
     * @return bool
     */
    public function monitorUntilSynced(OutputInterface $output): bool
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
            $progressBar = new ProgressBar($output, 100);
            $reStatus = '/Status: (.*)$/i';
            $reProgress = '/Staging files on beta: (\d+)%/i';
            $process->start();
            $progressBar->start();
            $process->waitUntil(
                function (string $type, string $buffer) use ($reStatus, $reProgress, $progressBar) {
                    preg_match($reStatus, $buffer, $statusMatch);
                    if (isset($statusMatch[1])) {
                        preg_match($reProgress, $statusMatch[1], $progressMatch);
                        if (!empty($progressMatch)) {
                            $progressBar->setProgress($progressMatch[1]);
                        }

                        return rtrim($statusMatch[1]) === 'Watching for changes';
                    }

                    return false;
                }
            );
            $progressBar->finish();

            return true;
        } catch (ProcessTimedOutException $e) {
            return false;
        }
    }
}
