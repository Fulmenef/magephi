<?php

namespace Magephi\Component;

use Magephi\Entity\Environment;
use Symfony\Component\Process\Process;

class DockerCompose
{
    /** @var ProcessFactory */
    private $processFactory;

    /**
     * DockerCompose constructor.
     *
     * @param ProcessFactory $processFactory
     */
    public function __construct(ProcessFactory $processFactory)
    {
        $this->processFactory = $processFactory;
    }

    /**
     * Open a TTY terminal to the given container.
     *
     * @param string      $container   Container name
     * @param string      $arguments
     * @param Environment $environment
     *
     * @throws \Exception
     */
    public function openTerminal(string $container, string $arguments, Environment $environment)
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
            $environment->getDockerRequiredVariables()
        );
    }
}
