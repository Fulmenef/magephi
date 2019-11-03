<?php

namespace Magphi\Command\Magento;

use Magphi\Component\DockerCompose;
use Magphi\Component\ProcessFactory;
use Magphi\Helper\Installation;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StartCommand extends AbstractMagentoCommand
{
    protected $command = 'start';

    /** @var Installation */
    private $installation;

    public function __construct(
        ProcessFactory $processFactory,
        DockerCompose $dockerCompose,
        Installation $installation,
        string $name = null
    ) {
        parent::__construct($processFactory, $dockerCompose, $name);
        $this->installation = $installation;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->installation->setOutputInterface($output);
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Start environment, equivalent to <fg=yellow>make start</>')
            ->setHelp(
                'This command allows you to start your Magento 2 environment. It must have been installed before.'
            )
        ;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return null|int
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        try {
            $this->installation->start();

            return null;
        } catch (\Exception $e) {
            $this->interactive->error($e->getMessage());

            return 1;
        }
    }
}
