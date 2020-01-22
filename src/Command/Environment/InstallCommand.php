<?php

namespace Magephi\Command\Environment;

use Exception;
use Magephi\Command\AbstractCommand;
use Magephi\Component\DockerCompose;
use Magephi\Component\Mutagen;
use Magephi\Component\Process;
use Magephi\Component\ProcessFactory;
use Magephi\Entity\Environment;
use Magephi\Entity\System;
use Magephi\Exception\ComposerException;
use Magephi\Exception\EnvironmentException;
use Magephi\Exception\ProcessException;
use Magephi\Helper\Database;
use Magephi\Helper\Make;
use Magephi\Kernel;
use Nadar\PhpComposerReader\ComposerReader;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

/**
 * Command to install the Magento2 project. It'll check if the prerequisites are filled before installing dependencies
 * and setup the Docker environment.
 */
class InstallCommand extends AbstractEnvironmentCommand
{
    public const DOCKER_LOCAL_ENV = 'docker/local/.env';

    protected string $command = 'install';

    private string $envContent;

    private string $nginxContent;

    private Environment $environment;

    private Make $make;

    private Mutagen $mutagen;

    private System $prerequisite;

    private Database $database;

    private LoggerInterface $logger;

    public function __construct(
        ProcessFactory $processFactory,
        DockerCompose $dockerCompose,
        Make $make,
        Database $database,
        Mutagen $mutagen,
        Environment $environment,
        System $system,
        LoggerInterface $logger,
        string $name = null
    ) {
        parent::__construct($processFactory, $dockerCompose, $name);
        $this->make = $make;
        $this->mutagen = $mutagen;
        $this->environment = $environment;
        $this->prerequisite = $system;
        $this->database = $database;
        $this->logger = $logger;
    }

    public function getPrerequisites(): array
    {
        return [];
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Install the Magento2 project environment in the current directory.')
            ->setHelp('This command allows you to install the Magento 2 environment of the current project.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->checkPrerequisites();

            $composer = $this->installDependencies();

            $this->interactive->newLine();

            $this->prepareEnvironment($composer);

            $this->buildContainers();

            $this->interactive->newLine(2);

            $this->startContainers();

            if ($imported = $this->importDatabase()) {
                $this->updateDatabase();
            }
        } catch (Exception $e) {
            if ($e->getMessage() !== '') {
                $this->interactive->error($e->getMessage());
            }

            return AbstractCommand::CODE_ERROR;
        }

        $this->interactive->newLine(2);

        $this->interactive->success('Your environment has been successfully installed.');

        $serverName = $this->environment->getServerName(true);
        if ($imported && $this->environment->hasMagentoEnv()) {
            $this->interactive->success(
                "Your project is ready, you can access it on {$serverName}"
            );
        } else {
            if (!$this->environment->hasMagentoEnv()) {
                $this->interactive->warning(
                    'The file app/etc/env.php is missing. Install Magento or retrieve it from another project.'
                );
            }
            if (!$imported) {
                $this->interactive->warning('No database has been imported, install Magento or import the database.');
            }
            $this->interactive->success(
                "Your project is almost ready, it'll will be available on {$serverName}"
            );
        }

        return AbstractCommand::CODE_SUCCESS;
    }

    /**
     * Ensure environment is ready.
     */
    protected function checkPrerequisites(): void
    {
        // Run environment checks.
        $this->interactive->section('Environment check');

        $prerequisites = $this->prerequisite->getBinaryPrerequisites();
        foreach ($prerequisites as $component => $info) {
            $this->check(
                $component . ' is installed.',
                $component . ' is missing.',
                function () use ($info) {
                    return $info['status'];
                },
                $info['mandatory']
            );
        }

        $prerequisites = $this->prerequisite->getServicesPrerequisites();
        foreach ($prerequisites as $component => $info) {
            $this->check(
                $component . ' is running.',
                $component . ' must be started.',
                function () use ($info) {
                    return $info['status'];
                },
                $info['mandatory']
            );
        }
    }

    /**
     * @return ComposerReader
     */
    protected function installDependencies(): ComposerReader
    {
        $this->interactive->section('Installing dependencies');
        /** @var ComposerReader $composer */
        $composer = new ComposerReader('composer.json');
        if (!$composer->canRead()) {
            throw new ComposerException('Unable to read json.');
        }
        $composer->runCommand('install --ignore-platform-reqs -o');

        return $composer;
    }

    /**
     * @param ComposerReader $composer
     */
    protected function prepareEnvironment(ComposerReader $composer): void
    {
        $this->interactive->section('Configuring docker environment');
        if ($this->environment->__get('distEnv') === null) {
            $this->interactive->section('Creating docker local directory');
            $composer->runCommand('exec docker-local-install');
            $this->environment->autoLocate();
        }

        $configureEnv = $this->environment->__get('localEnv') === null ?:
            $this->interactive->confirm(
                'An existing docker <fg=yellow>.env</> file already exist, do you want to override it ?',
                false
            );

        if ($configureEnv) {
            $this->prepareDockerEnv();
        }

        $serverName = $this->chooseServerName();
        $this->setupHost($serverName);
    }

    /**
     * @throws EnvironmentException
     */
    protected function prepareDockerEnv(): void
    {
        $distEnv = $this->environment->__get('distEnv');
        if (!\is_string($distEnv)) {
            throw new EnvironmentException(
                'env.dist does not exist. Ensure emakinafr/docker-magento2 is present in dependencies.'
            );
        }
        copy($distEnv, self::DOCKER_LOCAL_ENV);
        $this->environment->__set('localEnv', self::DOCKER_LOCAL_ENV);
        $content = file_get_contents(self::DOCKER_LOCAL_ENV);
        if ($content === false) {
            throw new FileException('Local env not found.');
        }
        $this->envContent = $content;

        $dockerfile = $this->environment->__get('phpDockerfile');
        if (!\is_string($dockerfile)) {
            throw new EnvironmentException(
                'PHP Dockerfile does not exist. Ensure emakinafr/docker-magento2 is present in dependencies.'
            );
        }
        $file = file_get_contents($dockerfile);
        if ($file === false) {
            throw new FileException('PHP Dockerfile not found.');
        }

        preg_match_all('/FROM .* as (\w*)/m', $file, $images);
        $images = $images[1];
        array_unshift($images, 'phoney');
        unset($images[0]);

        $image = $this->interactive->choice('Select the image you want to use:', $images, $images[1]);
        $replacement = preg_replace('/(DOCKER_PHP_IMAGE=)(\w+)/i', "$1{$image}", $this->envContent);
        if ($replacement === null) {
            throw new EnvironmentException('Error while configuring Docker PHP Image.');
        }
        $this->envContent = $replacement;
        $this->environment->__set('phpImage', $image);

        $imageType = explode('_', $image);
        if (\count($imageType) > 2) {
            $imageType = array_pop($imageType);
            if (!\is_string($imageType)) {
                throw new EnvironmentException('Image type is undefined.');
            }

            if ($this->interactive->confirm("Do you want to configure <fg=yellow>{$imageType}</> ?")) {
                $this->configureEnv($imageType);
            }
        }

        if ($this->interactive->confirm('Do you want to configure <fg=yellow>MySQL</> ?')) {
            $this->configureEnv('mysql');
        }

        file_put_contents(self::DOCKER_LOCAL_ENV, $this->envContent);
    }

    protected function buildContainers(): void
    {
        $this->interactive->section('Building containers');
        $process = $this->make->build();

        if (!$process->getProcess()->isSuccessful()) {
            $this->interactive->newLine(2);
            if ($process->getExitCode() === Process::CODE_TIMEOUT) {
                $errorMessage = 'Build timeout, run directly `make build` to build the environment.';
            } else {
                $this->interactive->note(
                    [
                        "Ensure you're not using a deleted branch for package emakinafr/docker-magento2.",
                        'This issue may came from a missing package in the PHP dockerfile after a version upgrade.',
                    ]
                );
                $errorMessage = $process->getProcess()->getErrorOutput();
            }

            throw new ProcessException($errorMessage);
        }
    }

    /**
     * @throws ProcessException
     */
    protected function startContainers(): void
    {
        $this->interactive->section('Starting environment');

        $process = $this->make->start(true);
        if (!$process->getProcess()->isSuccessful() && $process->getExitCode() !== Process::CODE_TIMEOUT) {
            $this->interactive->newLine(2);

            $this->logger->error($process->getProcess()->getErrorOutput());

            throw new ProcessException(
                'An error occured during the start, ensure there is no other containers running. To have more detail, run the command with verbosity.'
            );
        }
        if ($process->getExitCode() === Process::CODE_TIMEOUT) {
            $this->make->startMutagen();
            $this->interactive->newLine();
            $this->interactive->text('Containers are up.');
            $this->interactive->section('File synchronization');
            $synced = $this->mutagen->monitorUntilSynced();
            if (!$synced) {
                throw new ProcessException(
                    'Something happened during the sync, check the situation with <fg=yellow>mutagen monitor</>.'
                );
            }
        }
    }

    /**
     * Configure environment variables in the .env file for a specific type.
     *
     * @param string $type Section to configure
     */
    private function configureEnv(string $type): void
    {
        $regex = "/({$type}\\w+)=(\\w*)/im";
        preg_match_all($regex, $this->envContent, $matches, PREG_SET_ORDER, 0);
        if (\count($matches)) {
            foreach ($matches as $match) {
                $conf = $this->interactive->ask(
                    $match[1],
                    $match[2] ?? null
                );
                if ($conf !== '' && $match[2] !== $conf) {
                    $pattern = "/({$match[1]}=)(\\w*)/i";
                    $content = preg_replace($pattern, "$1{$conf}", $this->envContent);
                    if (!\is_string($content)) {
                        throw new EnvironmentException('Error while configuring environment.');
                    }

                    $this->envContent = $content;
                }
            }
        } else {
            $this->interactive->warning(
                "Type <fg=yellow>{$type}</> has no configuration, maybe it is not supported yet or there's nothing to configure."
            );
        }
    }

    /**
     * @param string $serverName
     */
    private function setupHost(string $serverName): void
    {
        $hosts = file_get_contents('/etc/hosts');
        if (!\is_string($hosts)) {
            throw new FileException('/etc/hosts file not found.');
        }

        $serverName = "www.{$serverName}";
        preg_match_all("/{$serverName}/i", $hosts, $matches, PREG_SET_ORDER, 0);
        if (empty($matches)) {
            if ($this->interactive->confirm(
                'It seems like this host is not in your hosts file yet, do you want to add it ?'
            )) {
                $hosts .= sprintf("# Added by %s\n", Kernel::NAME);
                $hosts .= "127.0.0.1   {$serverName}\n";
                file_put_contents('/etc/hosts', $hosts);
                $this->interactive->text('Server added in your host file.');
            }
        }
    }

    /**
     * Let the user choose the server name of the project.
     *
     * @return string
     */
    private function chooseServerName(): string
    {
        $nginx = $this->environment->__get('nginxConf');
        if (!\is_string($nginx)) {
            throw new EnvironmentException(
                'nginx.conf does not exist. Ensure emakinafr/docker-magento2 is present in dependencies.'
            );
        }
        $content = file_get_contents($nginx);
        if (!\is_string($content)) {
            throw new EnvironmentException(
                "Something went wrong while reading {$nginx}, ensure the file is present."
            );
        }
        $this->nginxContent = $content;
        preg_match_all('/server_name (\S*);/m', $this->nginxContent, $matches, PREG_SET_ORDER, 0);
        $serverName = $matches[0][1];
        if ($this->interactive->confirm(
            "The server name is currently <fg=yellow>{$serverName}</>, do you want to change it ?",
            false
        )) {
            $serverName = $this->interactive->ask(
                'Specify the server name',
                $serverName
            );
            $pattern = '/(server_name )(\\S+)/i';
            $content = preg_replace($pattern, "$1{$serverName};", $this->nginxContent);
            if (!\is_string($content)) {
                throw new EnvironmentException('Error while preparing the nginx conf.');
            }

            $this->nginxContent = $content;
            file_put_contents($nginx, $this->nginxContent);
        }

        return $serverName;
    }

    /**
     * Import database from a file on the project. The file must be at the root or in a direct subdirectory.
     * TODO: Import database from Magento Cloud CLI if available.
     */
    private function importDatabase(): bool
    {
        $this->interactive->newLine(2);
        $this->interactive->section('Database');

        if ($this->interactive->confirm('Would you like to import a database ?')) {
            if ($files = glob('{*,*/*}{.sql,.sql.zip,.sql.gz,.sql.gzip}', GLOB_BRACE)) {
                if (\count($files) > 1) {
                    array_unshift($files, 'zero');
                    unset($files[0]);
                    $file = $this->interactive->choice(
                        'Multiple compatible files found, please select the correct one:',
                        $files
                    );
                } else {
                    $file = $files[0];
                    if (!$this->interactive->confirm("{$file} is going to be imported, ok ?")) {
                        $file = null;
                    }
                }
                if ($file !== null) {
                    if ($database = $this->environment->getDatabase()) {
                        try {
                            $process = $this->database->import($this->environment->getDatabase(), $file);

                            if (!$process->getProcess()->isSuccessful()) {
                                throw new ProcessException($process->getProcess()->getErrorOutput());
                            }

                            return true;
                        } catch (Exception $e) {
                            $this->interactive->error($e->getMessage());
                        }
                    }
                    $this->interactive->text("No database found in {$this->environment->__get('localEnv')}.");
                }
            } else {
                $this->interactive->text('No compatible file found.');
            }
        }
        $this->interactive->text(
            'If you want to import a database later, you can use the <fg=yellow>import</> command.'
        );

        return false;
    }

    /**
     * Update database url with chosen server name.
     *
     * @return bool
     */
    private function updateDatabase(): bool
    {
        if ($this->interactive->confirm('Do you want to update the urls ?', true)) {
            try {
                $process = $this->database->updateUrls($this->environment->getDatabase());
            } catch (Exception $e) {
                $this->interactive->error($e->getMessage());

                return false;
            }

            if (!$process->getProcess()->isSuccessful()) {
                $this->interactive->error($process->getProcess()->getErrorOutput());

                return false;
            }

            return true;
        }

        return false;
    }
}
