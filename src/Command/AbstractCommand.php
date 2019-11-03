<?php

namespace Magphi\Command;

use Magphi\Component\DockerCompose;
use Magphi\Component\ProcessFactory;
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

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->interactive = new SymfonyStyle($input, $output);
        parent::initialize($input, $output);
    }
}
