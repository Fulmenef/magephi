<?php

namespace Magephi\Command\Docker;

use Magephi\Command\AbstractCommand;
use Magephi\Entity\Environment;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractDockerCommand extends AbstractCommand
{
    protected $service = '';
    protected $arguments = '';

    protected function configure()
    {
        $this
            ->setName('magphi:docker:'.$this->service)
            ->setAliases([$this->service])
            ->setDescription("Open a terminal to the {$this->service} container.")
            ->setHelp("This command allows you to connect to the {$this->service} container.")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $environment = new Environment();
        $environment->autoLocate();

        try {
            $this->dockerCompose->openTerminal($this->service, $this->arguments, $environment);
        } catch (\Exception $e) {
            $this->interactive->error($e->getMessage());
        }
    }
}
