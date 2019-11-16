<?php

namespace Magephi\Command\Magento;

use Magephi\Entity\Environment;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to stop the environment. The install command must have been executed before.
 */
class StopCommand extends AbstractMagentoCommand
{
    protected $command = 'stop';

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Stop environment, equivalent to <fg=yellow>make stop</>')
            ->setHelp(
                'This command allows you to stop your Magento 2 environment. It must have been installed before.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $environment = new Environment();
        $environment->autoLocate();

        $this->interactive->section('Stopping environment');

        $process = $this->processFactory->runProcessWithProgressBar(
            ['make', 'stop'],
            60,
            function ($type, $buffer) {
                return stripos($buffer, 'stopping') && stripos($buffer, 'done');
            },
            $output,
            $environment->getContainers() + 1
        );
        $this->interactive->newLine(2);

        if ($process->isSuccessful()) {
            $this->interactive->success('Environment stopped.');
        } else {
            $this->interactive->error(
                [
                    "Environment couldn't be stopped: ",
                    $process->getErrorOutput(),
                ]
            );

            return 1;
        }

        return null;
    }
}
