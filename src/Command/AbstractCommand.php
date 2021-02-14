<?php

declare(strict_types=1);

namespace Magephi\Command;

use Humbug\SelfUpdate\Strategy\GithubStrategy;
use Humbug\SelfUpdate\Updater;
use Magephi\Application;
use Magephi\Component\DockerCompose;
use Magephi\Component\ProcessFactory;
use Magephi\Entity\Environment\Manager;
use Magephi\Entity\System;
use Magephi\EventListener\CommandListener;
use Magephi\Exception\EnvironmentException;
use Magephi\Helper\UpdateHandler;
use Magephi\Kernel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractCommand extends Command
{
    protected SymfonyStyle $interactive;

    protected ProcessFactory $processFactory;

    protected DockerCompose $dockerCompose;

    protected Manager $manager;

    public function __construct(ProcessFactory $processFactory, DockerCompose $dockerCompose, Manager $manager)
    {
        $this->processFactory = $processFactory;
        $this->dockerCompose = $dockerCompose;
        $this->manager = $manager;
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    public function setName(string $name)
    {
        $name = $name === 'default' ? $name : 'magephi:' . $name;

        return parent::setName($name);
    }

    /**
     * Check if there's a new version available.
     *
     * @return null|string Return latest new version or null if nothing is available
     */
    public function checkNewVersionAvailable(): ?string
    {
        /** @var Application $app */
        $app = $this->getApplication();
        $version = $app->getVersion();

        if (Kernel::getMode() === 'dev') {
            return null;
        }

        $updater = new Updater(null, false);
        $strategy = new GithubStrategy();
        $strategy->setPackageName(UpdateHandler::PACKAGE_NAME);
        $strategy->setPharName(UpdateHandler::FILE_NAME);
        $strategy->setCurrentLocalVersion($version);
        $updater->setStrategyObject($strategy);

        return $updater->hasUpdate() ? $updater->getNewVersion() : null;
    }

    /**
     * Checks a condition, outputs a message, and exits if failed.
     *
     * @param string   $success   the success message
     * @param string   $failure   the failure message
     * @param callable $condition the condition to check
     * @param bool     $exit      whether to exit on failure
     *
     * @throws EnvironmentException
     */
    public function check(string $success, string $failure, callable $condition, bool $exit = true): void
    {
        if ($condition()) {
            $this->interactive->writeln("<fg=green>  [*] {$success}</>");
        } elseif (!$exit) {
            $this->interactive->writeln("<fg=yellow>  [!] {$failure}</>");
        } else {
            throw new EnvironmentException($failure);
        }
    }

    /**
     * Contain system prerequisite for the command. Must always follow the same structure.
     * Must contains the exact name of prerequisites defined in the System class.
     *
     * @return array[]
     *
     * @see CommandListener Listener using the variable to check the prerequisites
     * @see System Class containing known prerequisites.
     */
    public function getPrerequisites(): array
    {
        return ['binary' => ['Docker', 'Docker-Compose'], 'service' => ['Docker']];
    }

    protected function configure(): void
    {
        $this->addOption(
            'no-timeout',
            null,
            InputOption::VALUE_NONE,
            'Specify this option to remove timeout limitations.'
        );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->interactive = new SymfonyStyle($input, $output);
        $this->manager->setOutput($this->interactive);

        $update = $this->checkNewVersionAvailable();
        if ($update !== null) {
            $this->interactive->note(
                "A new version is available, use the update command to update to version {$update}"
            );
            if ($this->interactive->confirm('Would you like to update ?', false)) {
                $updateHandler = new UpdateHandler();
                if ($updateHandler->handle()) {
                    $this->interactive->success('Application updated, please relaunch your command');

                    exit(self::SUCCESS); // Necessary to bypass Symfony post command check  and avoid errors
                }
                $this->interactive->warning(
                    'Something went wrong, try again later or by using the update command'
                );
            }
        }
    }
}
