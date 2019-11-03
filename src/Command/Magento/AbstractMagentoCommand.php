<?php

namespace Magphi\Command\Magento;

use Magphi\Command\AbstractCommand;

abstract class AbstractMagentoCommand extends AbstractCommand
{
    protected $command = '';

    protected function configure()
    {
        $this
            ->setName('magphi:'.$this->command)
            ->setAliases([$this->command])
        ;
    }
}
