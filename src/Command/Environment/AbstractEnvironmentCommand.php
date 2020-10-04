<?php

declare(strict_types=1);

namespace Magephi\Command\Environment;

use Magephi\Command\AbstractCommand;

abstract class AbstractEnvironmentCommand extends AbstractCommand
{
    protected string $command = '';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('environment:' . $this->command)
            ->setAliases([$this->command]);
    }
}
