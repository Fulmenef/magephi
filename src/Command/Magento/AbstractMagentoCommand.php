<?php

namespace Magephi\Command\Magento;

use Magephi\Command\AbstractCommand;

/**
 * Abstract class for the Magento commands. Environment must be started before use.
 */
abstract class AbstractMagentoCommand extends AbstractCommand
{
    /** @var string */
    protected $command = '';

    protected function configure(): void
    {
        $this
            ->setName('magephi:magento:' . $this->command)
            ->setAliases(['magento:' . $this->command]);
    }
}
