<?php

declare(strict_types=1);

namespace Magephi\Component;

use Error;
use Magephi\Command\Docker\PhpCommand;
use Magephi\Entity\Environment\EnvironmentInterface;
use Magephi\Exception\EnvironmentException;
use Magephi\Exception\ProcessException;
use Psr\Log\LoggerInterface;

class DockerCompose
{
    private ProcessFactory $processFactory;

    private EnvironmentInterface $environment;

    private LoggerInterface $logger;

    public function __construct(ProcessFactory $processFactory, LoggerInterface $logger)
    {
        $this->processFactory = $processFactory;
        $this->logger = $logger;
    }

    /**
     * Open a TTY terminal to the given container.
     *
     * @param string               $container Container name
     * @param array<string,string> $arguments
     *
     * @throws EnvironmentException
     * @throws ProcessException
     */
    public function openTerminal(string $container, array $arguments): void
    {
        if (!$this->isContainerUp($container)) {
            throw new EnvironmentException(sprintf('The container %s is not started.', $container));
        }

        if (!\Symfony\Component\Process\Process::isTtySupported()) {
            throw new ProcessException(
                'TTY is not supported, ensure you\'re running the application from the command line.'
            );
        }

        $commands = ['docker', 'exec', '--interactive', '--tty'];
        if (!empty($arguments)) {
            foreach ($arguments as $argument => $value) {
                $commands[] = "--{$argument}={$value}";
            }
        }
        $commands = array_merge($commands, ["$(docker compose ps --quiet {$container})", 'bash', '--login']);

        $this->processFactory->runInteractiveProcess(
            $commands,
            null,
            $this->environment->getDockerRequiredVariables()
        );
    }

    /**
     * Test if the given container is up or not.
     *
     * @param string $container
     *
     * @return bool
     */
    public function isContainerUp(string $container): bool
    {
        $command = "docker ps -q --no-trunc | grep $(docker-compose ps -q {$container})";
        $commands = explode(' ', $command);

        try {
            $process =
                $this->processFactory->runProcess(
                    $commands,
                    10,
                    $this->environment->getDockerRequiredVariables(),
                    true
                );
        } catch (Error $e) {
            $this->logger->error($e->getMessage());

            throw new EnvironmentException('Environment is not defined, install the environment first.');
        }

        return $process->getProcess()->isSuccessful() && !empty($process->getProcess()->getOutput());
    }

    /**
     * Execute a command in the specified container.
     *
     * @param string $container
     * @param string $command
     * @param bool   $createOnly If provided, return an instance of Process without execution
     *
     * @return Process
     */
    public function executeContainerCommand(
        string $container,
        string $command,
        bool $createOnly = false
    ): Process {
        if (!$this->isContainerUp($container)) {
            throw new EnvironmentException(sprintf('The container %s is not started.', $container));
        }

        $arguments = [];
        if ($container === 'php') {
            $arguments = ['-u', PhpCommand::ARGUMENT_WWW_DATA];
        }

        $finalCommand =
            array_merge(
                ['docker-compose', 'exec'],
                $arguments,
                ['-T', $container, 'sh', '-c', sprintf('"%s"', escapeshellcmd($command))]
            );

        if ($createOnly) {
            /** @var Process $process */
            $process = $this->processFactory->createProcess(
                $finalCommand,
                600,
                $this->environment->getDockerRequiredVariables(),
                true
            );
        } else {
            $process = $this->processFactory->runProcess(
                $finalCommand,
                600,
                $this->environment->getDockerRequiredVariables(),
                true
            );
        }

        return $process;
    }

    /**
     * Execute a docker-compose command like `ps` or `logs`.
     *
     * @param string $command
     *
     * @return Process
     */
    public function executeGlobalCommand(string $command): Process
    {
        $commands = explode(' ', $command);

        return $this->processFactory->runProcess(
            array_merge(['docker-compose'], $commands),
            600,
            $this->environment->getDockerRequiredVariables(),
            true
        );
    }

    /**
     * Restart the given container.
     *
     * @param string $container
     *
     * @return bool
     */
    public function restartContainer(string $container): bool
    {
        $process = $this->processFactory->runProcess(
            ['docker-compose', 'restart', $container],
            60,
            $this->environment->getDockerRequiredVariables()
        );

        return $process->getProcess()->isSuccessful();
    }

    /**
     * List of containers and their status.
     *
     * @return string[]
     */
    public function list(): array
    {
        $process = $this->processFactory->runProcess(
            ['docker-compose', 'ps'],
            60,
            $this->environment->getDockerRequiredVariables()
        );

        $regex = '/^(?![ -])(\S+).+(?=running|exited)(\S+)/mi';

        $output = $process->getProcess()->getOutput();

        preg_match_all($regex, $output, $matches, PREG_SET_ORDER, 0);

        $containers = [];
        foreach ($matches as $match) {
            $containers[$match[1]] = $match[2];
        }

        return $containers;
    }

    /**
     * @param EnvironmentInterface $environment
     *
     * @return DockerCompose
     */
    public function setEnvironment(EnvironmentInterface $environment): self
    {
        $this->environment = $environment;

        return $this;
    }
}
