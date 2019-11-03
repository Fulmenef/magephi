<?php

namespace Magephi\Command;

use Magephi\Component\DockerCompose;
use Magephi\Component\ProcessFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractCommand extends Command
{
    /** @var SymfonyStyle */
    protected $interactive;

    /** @var ProcessFactory */
    protected $processFactory;

    /** @var DockerCompose */
    protected $dockerCompose;

    public function __construct(ProcessFactory $processFactory, DockerCompose $dockerCompose, string $name = null)
    {
        $this->processFactory = $processFactory;
        $this->dockerCompose = $dockerCompose;
        parent::__construct($name);
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->interactive = new SymfonyStyle($input, $output);
        parent::initialize($input, $output);
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
