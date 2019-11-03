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

    /**
     * Checks a condition, outputs a message, and exits if failed.
     *
     * @param string   $success   the success message
     * @param string   $failure   the failure message
     * @param callable $condition the condition to check
     * @param bool     $exit      whether to exit on failure
     */
    protected function check($success, $failure, $condition, $exit = true)
    {
        if ($condition()) {
            $this->interactive->writeln("<fg=green>  [*] {$success}</>");
        } elseif (!$exit) {
            $this->interactive->writeln("<fg=yellow>  [!] {$failure}</>");
        } else {
            $this->interactive->writeln("<fg=red>  [X] {$failure}</>");
            exit(1);
        }
    }
}
