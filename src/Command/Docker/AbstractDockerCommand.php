<?php

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
    /** @var string */
    protected $service = '';
    /** @var string */
    protected $arguments = '';

    protected function configure(): void
    {
        $this
            ->setName('magephi:docker:' . $this->service)
            ->setAliases([$this->service])
            ->setDescription("Open a terminal to the {$this->service} container.")
            ->setHelp("This command allows you to connect to the {$this->service} container.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->dockerCompose->openTerminal($this->service, $this->arguments);
        } catch (EnvironmentException | ProcessException $e) {
            $this->interactive->error($e->getMessage());

            return AbstractCommand::CODE_ERROR;
        }

        return AbstractCommand::CODE_SUCCESS;
    }
}
