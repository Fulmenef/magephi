<?php

declare(strict_types=1);

namespace Magephi\Command;

use Exception;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DefaultCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected static $defaultName = 'magephi:default';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setDescription('Wrapper of the default "list" command');
        $this->setHidden(true);
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var Application $application */
        $application = $this->getApplication();

        $command = $application->find('list');
        $arguments = ['namespace' => 'magephi'];

        $listInput = new ArrayInput($arguments);

        return $command->run($listInput, $output);
    }
}
