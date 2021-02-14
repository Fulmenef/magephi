<?php

declare(strict_types=1);

namespace Magephi\Command\Environment;

use Magephi\Component\DockerCompose;
use Magephi\Component\Mutagen;
use Magephi\Component\ProcessFactory;
use Magephi\Entity\Environment\Manager;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to give a quick status of the project.
 */
class StatusCommand extends AbstractEnvironmentCommand
{
    protected string $command = 'status';

    private Mutagen $mutagen;

    public function __construct(
        ProcessFactory $processFactory,
        DockerCompose $dockerCompose,
        Manager $manager,
        Mutagen $mutagen
    ) {
        parent::__construct($processFactory, $dockerCompose, $manager);
        $this->mutagen = $mutagen;
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Give the status of the project')
            ->setHelp(
                'This command allows you to know the status of the project (containers, synchronisation, Magento, database...).'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Containers
        $containers = $this->dockerCompose->list();
        $table = new Table($output);
        $table->setHeaders(['Container', 'Status']);
        foreach ($containers as $container => $status) {
            $table->addRow([$container, $status]);
        }
        $table->render();

        // Mutagen
        if ($this->mutagen->isExistingSession()) {
            if ($this->mutagen->isSynced()) {
                $mutagenStatus = 'Synchronized';
            } elseif ($this->mutagen->isPaused()) {
                $mutagenStatus = 'Session paused';
            } else {
                $mutagenStatus = 'Synchronization in progress';
            }
        } else {
            $mutagenStatus = 'Session does not exist';
        }

        $this->interactive->writeln('Mutagen status: <info>' . $mutagenStatus . '</info>');

        return self::SUCCESS;
    }
}
