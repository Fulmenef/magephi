<?php

namespace Magephi\Component;

use Magephi\Entity\Environment;
use Symfony\Component\Process\Process;

class DockerCompose
{
    /** @var ProcessFactory */
    private $processFactory;
    /** @var Environment */
    private $environment;

    /**
     * DockerCompose constructor.
     *
     * @param ProcessFactory $processFactory
     * @param Environment    $environment
     */
    public function __construct(ProcessFactory $processFactory, Environment $environment)
    {
        $this->processFactory = $processFactory;
        $this->environment = $environment;
    }

    /**
     * Open a TTY terminal to the given container.
     *
     * @param string $container Container name
     * @param string $arguments
     *
     * @throws \Exception
     */
    public function openTerminal(string $container, string $arguments): void
    {
        if (!Process::isTtySupported()) {
            throw new \Exception("TTY is not supported, ensure you're running the application from the command line.");
        }
        $commands = ['docker-compose', 'exec'];
        if ($arguments !== '') {
            $commands = array_merge($commands, ['-u', $arguments]);
        }
        $this->processFactory->runInteractiveProcess(
            array_merge($commands, [$container, 'sh', '-l']),
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
        $process = $this->processFactory->runProcess($commands, 10, $this->environment->getDockerRequiredVariables(), true);

        return $process->getProcess()->isSuccessful() && !empty($process->getProcess()->getOutput());
    }
}
