<?php

declare(strict_types=1);

namespace Magephi\Command\Magento;

use Magephi\Command\AbstractCommand;

/**
 * Abstract class for the Magento commands. Environment must be started before use.
 */
abstract class AbstractMagentoCommand extends AbstractCommand
{
    protected string $command = '';

    protected function configure(): void
    {
        $this->setName('magento:' . $this->command);
    }
}
