<?php

namespace Magephi\Command\Magento;

use Magephi\Command\AbstractCommand;

abstract class AbstractMagentoCommand extends AbstractCommand
{
    protected $command = '';

    protected function configure()
    {
        $this
            ->setName('magephi:' . $this->command)
            ->setAliases([$this->command]);
    }
}
