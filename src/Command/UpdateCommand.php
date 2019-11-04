<?php

namespace Magephi\Command;

use Herrera\Phar\Update\Manager;
use Herrera\Phar\Update\Manifest;
use Magephi\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to upgrade the application to the latest version.
 *
 * @package Magephi\Command
 */
class UpdateCommand extends Command
{
    const MANIFEST_FILE = '';

    protected function configure()
    {
        $this
            ->setName('magphi:update')
            ->setAliases(['update'])
            ->setDescription('Updates Magphi to the latest version')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = $this->getApplication();
        if ($app instanceof Application) {
            $manager = new Manager(Manifest::loadFile(self::MANIFEST_FILE));
            $manager->update($app->getVersion(), true);
        } else {
            throw new \Exception(
                sprintf('Application must be type of %s, %s found.', Application::class, \gettype($app))
            );
        }
    }
}
