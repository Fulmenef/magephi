<?php

namespace Magephi\Command;

use Humbug\SelfUpdate\Strategy\GithubStrategy;
use Humbug\SelfUpdate\Updater;
use Magephi\Application;
use Magephi\Component\DockerCompose;
use Magephi\Component\ProcessFactory;
use Magephi\Entity\System;
use Magephi\EventListener\CommandListener;
use Magephi\Exception\EnvironmentException;
use Magephi\Kernel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractCommand extends Command
{
    public const CODE_SUCCESS = 0;
    public const CODE_ERROR = 1;

    public const FILE_NAME = 'magephi.phar';
    public const PACKAGE_NAME = 'fulmenef/magephi';

    protected SymfonyStyle $interactive;

    protected ProcessFactory $processFactory;

    protected DockerCompose $dockerCompose;

    public function __construct(ProcessFactory $processFactory, DockerCompose $dockerCompose, string $name = null)
    {
        $this->processFactory = $processFactory;
        $this->dockerCompose = $dockerCompose;
        parent::__construct($name);
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
        $strategy->setPackageName(self::PACKAGE_NAME);
        $strategy->setPharName(self::FILE_NAME);
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
    public function check($success, $failure, $condition, $exit = true): void
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

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->interactive = new SymfonyStyle($input, $output);
        parent::initialize($input, $output);

        $update = $this->checkNewVersionAvailable();
        if ($update !== null) {
            $this->interactive->warning(
                "A new version is available, use the update command to update to version {$update}"
            );
        }
    }
}
