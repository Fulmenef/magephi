<?php

declare(strict_types=1);

namespace Magephi\Command\Environment;

use Magephi\Command\AbstractCommand;
use Magephi\Component\DockerCompose;
use Magephi\Component\ProcessFactory;
use Magephi\Entity\Environment\Manager;
use Magephi\Entity\System;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to scan environment and determine if all prerequisites are filled to install a Magento2 project using
 * emakinafr/docker-magento2.
 */
class CheckPrerequisiteCommand extends AbstractEnvironmentCommand
{
    protected string $command = 'prerequisites';

    private System $prerequisite;

    public function __construct(
        ProcessFactory $processFactory,
        DockerCompose $dockerCompose,
        Manager $manager,
        System $prerequisite
    ) {
        parent::__construct($processFactory, $dockerCompose, $manager);
        $this->prerequisite = $prerequisite;
    }

    public function getPrerequisites(): array
    {
        return [];
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Check if all prerequisites are installed on the system to run a Magento 2 project.')
            ->setHelp('This command allows you to know if your system is ready to handle Magento 2 projects.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = new Table($output);
        $table->setHeaders(['Component', 'Mandatory', 'Status', 'Comment']);

        $ready = true;
        foreach ($this->prerequisite->getBinaryPrerequisites() as $component => $info) {
            if (!$info['status']) {
                $ready = false;
            }
            $info['mandatory'] = $info['mandatory'] ? 'Yes' : 'No';
            $info['status'] = $info['status'] ? 'Installed' : 'Missing';
            $table->addRow(
                array_merge(
                    [$component],
                    [$info['mandatory']],
                    [$info['status']],
                    isset($info['comment']) ? [$info['comment']] : []
                )
            );
        }

        $table->render();

        if ($ready) {
            $this->interactive->success('Your system is ready.');

            return AbstractCommand::CODE_SUCCESS;
        }
        $this->interactive->error('Your system is not ready yet, install the missing components.');

        return AbstractCommand::CODE_ERROR;
    }
}
