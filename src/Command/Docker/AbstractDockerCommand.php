<?php

namespace Magephi\Command\Docker;

use Magephi\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Abstract class for the Docker commands. Used to connect to a container.
 */
abstract class AbstractDockerCommand extends AbstractCommand
{
    protected $service = '';
    protected $arguments = '';

    protected function configure()
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
            if (!$this->dockerCompose->isContainerUp($this->service)) {
                throw new \Exception("Container {$this->service} is not up");
            }
            $this->dockerCompose->openTerminal($this->service, $this->arguments);
        } catch (\Exception $e) {
            $this->interactive->error($e->getMessage());

            return AbstractCommand::CODE_ERROR;
        }

        return AbstractCommand::CODE_SUCCESS;
    }
}
