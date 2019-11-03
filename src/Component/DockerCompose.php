<?php

namespace Magephi\Component;

use Magephi\Entity\Environment;
use Symfony\Component\Process\Process;

class DockerCompose
{
    /** @var ProcessFactory */
    private $processFactory;

    public function __construct(ProcessFactory $processFactory)
    {
        $this->processFactory = $processFactory;
    }

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
