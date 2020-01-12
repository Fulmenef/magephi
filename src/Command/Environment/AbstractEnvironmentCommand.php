<?php

namespace Magephi\Command\Environment;

use Magephi\Command\AbstractCommand;

abstract class AbstractEnvironmentCommand extends AbstractCommand
{
    /** @var string */
    protected $command = '';

    protected function configure(): void
    {
        $this
            ->setName('magephi:environment:' . $this->command)
            ->setAliases([$this->command]);
    }
}
