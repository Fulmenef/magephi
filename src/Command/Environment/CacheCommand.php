<?php

declare(strict_types=1);

namespace Magephi\Command\Environment;

use Magephi\Command\AbstractCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to flush redis and magento caches.
 */
class CacheCommand extends AbstractEnvironmentCommand
{
    protected string $command = 'cache';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Flush redis and magento caches')
            ->setHelp('This command allows you to flush redis caches, Magento generated and cache directories');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->interactive->section('Redis');
        $progressBar = new ProgressBar($output, 2);
        $progressBar->display();
        $this->dockerCompose->executeContainerCommand('redis', 'redis-cli -n 1 FLUSHDB');
        $progressBar->advance();
        $this->dockerCompose->executeContainerCommand('redis', 'redis-cli -n 2 FLUSHDB');
        $progressBar->finish();

        $this->interactive->newLine(2);
        $this->interactive->section('Magento');
        $progressBar = new ProgressBar($output, 2);
        $progressBar->display();
        $this->dockerCompose->executeContainerCommand('php', 'rm -rf generated');
        $progressBar->advance();
        $this->dockerCompose->executeContainerCommand('php', 'rm -rf var/cache');
        $progressBar->finish();

        $this->interactive->newLine(2);
        $this->interactive->success('All caches are flushed');

        return AbstractCommand::CODE_SUCCESS;
    }
}
