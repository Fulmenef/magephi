<?php

namespace Magephi\Command;

use Humbug\SelfUpdate\Strategy\GithubStrategy;
use Humbug\SelfUpdate\Updater;
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
    protected function configure(): void
    {
        $this
            ->setName('magephi:update')
            ->setAliases(['update'])
            ->setDescription('Updates Magephi to the latest version');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
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

        if ($update !== null && Kernel::getMode() !== 'dev') {
            /** @var Application $app */
            $app = $this->getApplication();
            $version = $app->getVersion();

            $updater = new Updater(null, false);
            $strategy = new GithubStrategy();
            $strategy->setPackageName(self::PACKAGE_NAME);
            $strategy->setPharName(self::FILE_NAME);
            $strategy->setCurrentLocalVersion($version);
            $updater->setStrategyObject($strategy);

            $result = $updater->update();

            if ($result) {
                $this->interactive->success(
                    sprintf(
                        '%s has been upgraded to %s.',
                        Kernel::NAME,
                        $updater->getNewVersion()
                    )
                );

                exit(AbstractCommand::CODE_SUCCESS);    // Necessary to bypass Symfony post command check  and avoid errors
            }

            $updater->rollback();
            $this->interactive->error('Update canceled, something happened.');

            return AbstractCommand::CODE_ERROR;
        }

        $this->interactive->note('No new version to download');

        return AbstractCommand::CODE_SUCCESS;
    }
}
