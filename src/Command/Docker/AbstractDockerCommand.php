<?php

declare(strict_types=1);

namespace Magephi\Command\Docker;

use Magephi\Command\AbstractCommand;
use Magephi\Exception\EnvironmentException;
use Magephi\Exception\ProcessException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Abstract class for the Docker commands. Used to connect to a container.
 */
abstract class AbstractDockerCommand extends AbstractCommand
{
    protected string $service = '';

    protected string $arguments = '';

    protected function configure(): void
    {
        $this
            ->setName('docker:' . $this->service)
            ->setAliases([$this->service])
            ->setDescription("Open a terminal to the {$this->service} container.")
            ->setHelp("This command allows you to connect to the {$this->service} container.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->manager->openTerminal($this->service, $this->arguments);
        } catch (EnvironmentException | ProcessException $e) {
            $this->interactive->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
