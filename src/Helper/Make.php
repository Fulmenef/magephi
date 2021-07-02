<?php

declare(strict_types=1);

namespace Magephi\Helper;

use Magephi\Component\DockerCompose;
use Magephi\Component\Mutagen;
use Magephi\Component\Process;
use Magephi\Component\ProcessFactory;
use Magephi\Entity\Environment\EnvironmentInterface;
use Magephi\Exception\ProcessException;

class Make
{
    private EnvironmentInterface $environment;

    private ProcessFactory $processFactory;

    private DockerCompose $dockerCompose;

    private Mutagen $mutagen;

    public function __construct(
        DockerCompose $dockerCompose,
        ProcessFactory $processFactory,
        Mutagen $mutagen
    ) {
        $this->dockerCompose = $dockerCompose;
        $this->processFactory = $processFactory;
        $this->mutagen = $mutagen;
    }

    /**
     * @param EnvironmentInterface $environment
     */
    public function setEnvironment(EnvironmentInterface $environment): void
    {
        $this->environment = $environment;
        $this->dockerCompose->setEnvironment($environment);
        $this->mutagen->setEnvironment($environment);
    }

    /**
     * Run the `make start` command with a progress bar.
     *
     * @param bool $install
     *
     * @return Process
     */
    public function start(bool $install = false): Process
    {
        return $this->processFactory->runProcessWithProgressBar(
            ['make', 'start'],
            $_ENV['SHELL_VERBOSITY'] >= 1 ? 360 : 60,
            function (/* @noinspection PhpUnusedParameterInspection */ $type, $buffer) {
                return (stripos($buffer, 'Creating') !== false
                        && (
                            stripos($buffer, 'network') !== false
                            || stripos($buffer, 'volume') !== false
                            || stripos($buffer, 'done') !== false
                        ))
                    || (stripos($buffer, 'Starting') !== false && stripos($buffer, 'done') !== false);
            },
            $install ? $this->environment->getContainers() + $this->environment->getVolumes()
                + 2 : $this->environment->getContainers() + 1
        );
    }

    /**
     * Run the `make build` command with a progress bar.
     *
     * @return Process
     */
    public function build(): Process
    {
        return $this->processFactory->runProcessWithProgressBar(
            ['make', 'build'],
            600,
            function (/* @noinspection PhpUnusedParameterInspection */ $type, $buffer) {
                return stripos($buffer, 'skipping') || stripos($buffer, 'tagged');
            },
            $this->environment->getContainers()
        );
    }

    /**
     * Run the `make stop` command with a progress bar.
     *
     * @return Process
     */
    public function stop(): Process
    {
        return $this->processFactory->runProcessWithProgressBar(
            ['make', 'stop'],
            60,
            function ($type, $buffer) {
                return stripos($buffer, 'stopping') !== false && stripos($buffer, 'done') !== false;
            },
            $this->environment->getContainers() + 1
        );
    }

    /**
     * Run the `make purge` command with a progress bar.
     *
     * @return Process
     */
    public function purge(): Process
    {
        return $this->processFactory->runProcessWithProgressBar(
            ['make', 'purge'],
            300,
            function (/* @noinspection PhpUnusedParameterInspection */ $type, $buffer) {
                return
                    (
                        stripos($buffer, 'done')
                        && (
                            stripos($buffer, 'stopping') !== false
                            || stripos($buffer, 'removing') !== false
                        )
                    )
                    || (
                        stripos($buffer, 'removing') !== false
                        && (
                            stripos($buffer, 'network') !== false || stripos($buffer, 'volume') !== false
                        )
                    );
            },
            $this->environment->getContainers() * 2 + $this->environment->getVolumes() + 2
        );
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
