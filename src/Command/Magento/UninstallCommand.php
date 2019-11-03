<?php

namespace Magephi\Command\Magento;

use Exception;
use Magephi\Entity\Environment;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UninstallCommand extends AbstractMagentoCommand
{
    protected $command = 'uninstall';

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Uninstall the Magento2 project in the current directory.')
            ->setHelp('This command allows you to uninstall the Magento 2 project in the current directory.')
        ;
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $environnement = new Environment();
        $environnement->autoLocate();
        if ($this->interactive->confirm('Are you sure you want to uninstall this project ?', false)) {
            $content = file_get_contents($environnement->__get('dockerComposeFile'));
            if (!\is_string($content)) {
                $this->interactive->error($environnement->__get('dockerComposeFile').' is not found.');

                return 1;
            }
            $dockerComposeContent = $content;
            preg_match_all('/^( {2})\w+:$/im', $dockerComposeContent, $matches);
            $containers = $matches[0];
            preg_match_all('/^( {2})\w+: {}$/im', $dockerComposeContent, $matches);
            $volumes = $matches[0];

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
                \count($containers) * 2 + \count($volumes) + 2
            );

            $this->interactive->newLine();
            $this->interactive->success('This project has been successfully uninstalled.');
        }

        return null;
    }
}
