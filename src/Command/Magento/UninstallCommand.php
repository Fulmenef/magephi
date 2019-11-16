<?php

namespace Magephi\Command\Magento;

use Exception;
use Magephi\Entity\Environment;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to uninstall the environment. It simply remove volumes and destroy containers and the mutagen session.
 */
class UninstallCommand extends AbstractMagentoCommand
{
    protected $command = 'uninstall';

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Uninstall the Magento2 project in the current directory.')
            ->setHelp('This command allows you to uninstall the Magento 2 project in the current directory.');
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $environment = new Environment();
        $environment->autoLocate();
        if ($this->interactive->confirm('Are you sure you want to uninstall this project ?', false)) {

            $this->processFactory->runProcessWithProgressBar(
                ['make', 'purge'],
                300,
                function (/* @noinspection PhpUnusedParameterInspection */ $type, $buffer) {
                    return
                        (
                            stripos($buffer, 'done')
                            && (
                                stripos($buffer, 'stopping') !== false
                                || stripos($buffer, 'removing') !== false
                            )
                        )
                        || (
                            stripos($buffer, 'removing') !== false
                            && (
                                stripos($buffer, 'network') || stripos($buffer, 'volume')
                            )
                        );
                },
                $output,
                $environment->getContainers() * 2 + $environment->getVolumes() + 2
            );

            $this->interactive->newLine(2);
            $this->interactive->success('This project has been successfully uninstalled.');
        }

        return null;
    }
}
