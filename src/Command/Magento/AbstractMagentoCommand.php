<?php

namespace Magephi\Command\Magento;

use Magephi\Command\AbstractCommand;

abstract class AbstractMagentoCommand extends AbstractCommand
{
    /** @var string */
    protected $command = '';

    protected function configure(): void
    {
        $this
            ->setName('magephi:' . $this->command)
            ->setAliases([$this->command]);
    }
}
