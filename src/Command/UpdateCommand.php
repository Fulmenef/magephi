<?php

namespace Magephi\Command;

use Herrera\Phar\Update\Manager;
use Herrera\Phar\Update\Manifest;
use Magephi\Application;
use Magephi\Kernel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to upgrade the application to the latest version.
 */
class UpdateCommand extends Command
{
    const MANIFEST_FILE = 'https://fulmenef.github.io/magephi/manifest.json';

    /** @var SymfonyStyle */
    private $interactive;

    protected function configure()
    {
        $this
            ->setName('magephi:update')
            ->setAliases(['update'])
            ->setDescription('Updates Magephi to the latest version');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->interactive = new SymfonyStyle($input, $output);
        parent::initialize($input, $output);
    }

    /**
     * TODO: Ask before update and display new version at the end
     * TODO: Double check when a major version is released and the user accept to upgrade.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws \Exception
     *
     * @return null|int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = $this->getApplication();
        if ($app instanceof Application) {
            $manager = new Manager(Manifest::loadFile(self::MANIFEST_FILE));
            $result  = $manager->update($app->getVersion(), true, true);

            if ($result) {
                $this->interactive->success(sprintf('%s has been upgraded to the latest version', Kernel::NAME));
            } else {
                $this->interactive->note('No new version to download');
            }
        } else {
            throw new \Exception(
                sprintf('Application must be type of %s, %s found.', Application::class, \gettype($app))
            );
        }
    }
}
