<?php

namespace Magephi\Command;

use Herrera\Phar\Update\Manager;
use Herrera\Phar\Update\Manifest;
use Herrera\Version\Parser;
use Magephi\Application;
use Magephi\Kernel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to upgrade the application to the latest version.
 */
class UpdateCommand extends AbstractCommand
{
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
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $update = $this->checkNewVersionAvailable();

        if ($update !== null) {
            /** @var Application $app */
            $app = $this->getApplication();
            $newVersion = Parser::toVersion($update);
            $version = $app->getVersion();
            $parsedVersion = Parser::toVersion($version);
            if ($newVersion->getMajor() <= $parsedVersion->getMajor()
                || $this->interactive->confirm(
                    sprintf(
                        'You are going to update from version %s to %s, huge changes has been made, are you sure you want to upgrade ?',
                        $version,
                        $update
                    ),
                    false
                )) {
                $manager = new Manager(Manifest::loadFile(self::MANIFEST_FILE));
                $result = $manager->update($version, true, true);

                if ($result) {
                    $this->interactive->success(
                        sprintf(
                            '%s has been upgraded to %s. You will have to clear your caches with the cache:clear command.',
                            Kernel::NAME,
                            $update
                        )
                    );

                    return AbstractCommand::CODE_SUCCESS;
                }
            }
        }

        $this->interactive->note('No new version to download');

        return AbstractCommand::CODE_SUCCESS;
    }
}
