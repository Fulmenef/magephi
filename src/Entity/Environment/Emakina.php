<?php

declare(strict_types=1);

namespace Magephi\Entity\Environment;

use Magephi\Component\DockerHub;
use Magephi\Component\Mutagen;
use Magephi\Component\Process;
use Magephi\Component\Yaml;
use Magephi\Exception\DockerHubException;
use Magephi\Exception\EnvironmentException;
use Magephi\Exception\ProcessException;
use Magephi\Helper\Make;
use Magephi\Kernel;
use Nadar\PhpComposerReader\ComposerReader;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class Emakina implements EnvironmentInterface
{
    private string $dockerComposeFile = 'vendor/emakinafr/docker-magento2/docker-compose.yml';

    private ?string $dockerComposeContent = null;

    private ?int $containers = null;

    private ?int $volumes = null;

    private string $phpImage;

    private string $mysqlImage;

    private string $elasticsearchImage;

    private string $localEnv = 'docker/local/.env';

    private string $localEnvContent;

    private string $distEnv = 'docker/local/.env.dist';

    private string $nginxConf = 'docker/local/nginx.conf';

    private string $workingDir;

    private string $magentoEnv = 'app/etc/env.php';

    private SymfonyStyle $output;

    private Make $make;

    private Mutagen $mutagen;

    private Filesystem $filesystem;

    private DockerHub $dockerHub;

    public function __construct(Make $make, Mutagen $mutagen, Filesystem $filesystem, DockerHub $dockerHub, Yaml $yaml)
    {
        $this->make = $make;
        $make->setEnvironment($this);
        $this->mutagen = $mutagen;
        $this->filesystem = $filesystem;
        $this->dockerHub = $dockerHub;

        /** @var string $current */
        $current = posix_getcwd();
        // TODO Put it in Manager or another class ?
        $configFile = Kernel::getCustomDir() . '/config.yml';
        if ($filesystem->exists($configFile)) {
            $content = $yaml->read($configFile);
            if (!empty($content)) {
                $environments = array_keys($content['environment']);
                foreach ($environments as $env) {
                    if (substr($current, 0, \strlen($env)) === $env) {
                        chdir($env);
                        $this->workingDir = $env;

                        break;
                    }
                }
            }
        }

        if (!isset($this->workingDir)) {
            $this->workingDir = $current;
        }

        $this->autoLocate();
    }

    /**
     * Try to locate automatically the environment files.
     */
    public function autoLocate(): void
    {
        $localEnv = $this->getLocalEnvData();

        if (!empty($localEnv)) {
            $this->phpImage = $this->getVariableValue('DOCKER_PHP_IMAGE', $localEnv);
            $this->mysqlImage = $this->getVariableValue('DOCKER_MYSQL_IMAGE', $localEnv);
            $this->elasticsearchImage = $this->getVariableValue('DOCKER_ELASTICSEARCH_IMAGE', $localEnv);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setOutput(SymfonyStyle $output): self
    {
        $this->output = $output;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getDockerRequiredVariables(): array
    {
        return [
            'COMPOSE_FILE'               => './' . $this->dockerComposeFile,
            'COMPOSE_PROJECT_NAME'       => 'magento2_' . mb_strtolower($this->getWorkingDir()),
            'DOCKER_PHP_IMAGE'           => $this->phpImage,
            'DOCKER_MYSQL_IMAGE'         => $this->mysqlImage,
            'DOCKER_ELASTICSEARCH_IMAGE' => $this->elasticsearchImage,
            'PROJECT_LOCATION'           => getcwd() ?: '',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getBackupFiles(): array
    {
        return [$this->magentoEnv, $this->localEnv];
    }

    /**
     * Return the local .env content if defined.
     *
     * @throws FileNotFoundException
     * @throws EnvironmentException
     *
     * @return string
     */
    public function getLocalEnvData(): string
    {
        if ($this->filesystem->exists($this->localEnv)) {
            $content = file_get_contents($this->localEnv);
            if (!\is_string($content)) {
                throw new FileNotFoundException($this->localEnv . ' empty.');
            }
        }

        return isset($content) ? $content : '';
    }

    /**
     * {@inheritDoc}
     */
    public function getEnvData(string $name): string
    {
        $name = strtoupper($name);
        preg_match("/{$name}=(\\w+)/", $this->getLocalEnvData(), $match);

        return isset($match[1]) ? $match[1] : '';
    }

    /**
     * {@inheritDoc}
     */
    public function getDatabase(): string
    {
        if ($this->hasMagentoEnv()) {
            /** @var array<array> $env */
            $env = require_once $this->magentoEnv;
            if (isset($env['db']['connection']['default']['dbname'])) {
                return $env['db']['connection']['default']['dbname'];
            }
        }

        return $this->getEnvData('mysql_database');
    }

    /**
     * {@inheritDoc}
     */
    public function getContainers(): int
    {
        if (\is_int($this->containers)) {
            return $this->containers;
        }

        preg_match_all('/^( {2})\w+:$/im', $this->getDockerComposeContent(), $matches);
        $containers = \count($matches[0]);
        $this->containers = $containers;

        return $this->containers;
    }

    /**
     * Return the content of the docker-compose.yml.
     *
     * @return string
     */
    public function getDockerComposeContent(): string
    {
        if (\is_string($this->dockerComposeContent)) {
            return $this->dockerComposeContent;
        }

        $content = file_get_contents($this->dockerComposeFile ?: '');
        if ($content === false) {
            throw new FileNotFoundException('docker-compose.yml is not found.');
        }
        $this->dockerComposeContent = $content;

        return $this->dockerComposeContent;
    }

    public function isVariableUsed(string $variable): bool
    {
        return stripos($this->getDockerComposeContent(), '${' . $variable . '}') !== false;
    }

    /**
     * {@inheritDoc}
     */
    public function getVolumes(): int
    {
        if (\is_int($this->volumes)) {
            return $this->volumes;
        }

        preg_match_all('/^( {2})\w+: {}$/im', $this->getDockerComposeContent(), $matches);
        $volumes = \count($matches[0]);
        $this->volumes = $volumes;

        return $this->volumes;
    }

    /**
     * {@inheritDoc};
     */
    public function hasMagentoEnv(): bool
    {
        return $this->filesystem->exists($this->magentoEnv);
    }

    /**
     * Return true if vendor/emakinafr/docker-magento2/docker-compose.yml exists.
     *
     * @return bool
     */
    public function hasComposeFile(): bool
    {
        return $this->filesystem->exists($this->dockerComposeFile);
    }

    /**
     * {@inheritDoc}
     */
    public function getServerName($complete = false): string
    {
        $content = $this->getNginxConf();
        preg_match_all('/server_name (\S*);/m', $content, $matches, PREG_SET_ORDER, 0);

        $prefix = '';
        if ($complete) {
            $prefix = 'https://www.';
        }

        return $prefix . $matches[0][1];
    }

    /**
     * {@inheritDoc}
     */
    public function build(): bool
    {
        $process = $this->make->build();

        if (!$process->getProcess()->isSuccessful()) {
            if ($process->getExitCode() === Process::CODE_TIMEOUT) {
                throw new ProcessException(
                    'Build timeout, use the option --no-timeout or run directly `make build` to build the environment.'
                );
            }

            throw new EnvironmentException($process->getProcess()->getErrorOutput());
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function start(bool $install = false): bool
    {
        $process = $this->make->start($install);
        if (!$process->getProcess()->isSuccessful() && $process->getExitCode() !== Process::CODE_TIMEOUT) {
            // TODO Encapsulate error ?
            throw new EnvironmentException($process->getProcess()->getErrorOutput());
        }

        if ($process->getExitCode() === Process::CODE_TIMEOUT) {
            $this->make->startMutagen();
            $this->output->newLine();
            $this->output->text('Containers are up.');
            $this->output->section('File synchronization');
            $synced = $this->mutagen->monitorUntilSynced();
            if (!$synced) {
                throw new EnvironmentException(
                    'Something happened during the sync, check the situation with <fg=yellow>mutagen monitor</>.'
                );
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function stop(): bool
    {
        $process = $this->make->stop();

        if (!$process->getProcess()->isSuccessful()) {
            throw new EnvironmentException($process->getProcess()->getErrorOutput());
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function install(array $data = []): bool
    {
        $this->prepareEnvironment($data['composer']);

        $this->output->section('Building containers');

        try {
            $this->build();
        } catch (EnvironmentException $e) {
            $this->output->note(
                [
                    "Ensure you're not using a deleted branch for package emakinafr/docker-magento2.",
                    'This issue may came from a missing package in the PHP dockerfile after a version upgrade.',
                ]
            );

            throw $e;
        }

        $this->output->newLine(2);

        $this->output->section('Starting environment');
        $this->start(true);

        return true;
    }

    /**
     * @return string
     */
    public function getLocalEnv(): string
    {
        return $this->localEnv;
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(): bool
    {
        $process = $this->make->purge();
        $this->output->newLine(2);

        if (!$process->getProcess()->isSuccessful()) {
            $this->output->error(
                [
                    "Environment couldn't be uninstall: ",
                    $process->getProcess()->getErrorOutput(),
                ]
            );

            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getWorkingDir(bool $complete = false): string
    {
        return $complete ? $this->workingDir : basename($this->workingDir);
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): string
    {
        return 'emakina';
    }

    /**
     * Get content of the nginx configuration file.
     *
     * @return string
     */
    private function getNginxConf(): string
    {
        if (!\is_string($this->nginxConf)) {
            throw new EnvironmentException(
                'nginx.conf does not exist. Ensure emakinafr/docker-magento2 is present in dependencies.'
            );
        }

        $content = file_get_contents($this->nginxConf);
        if (!\is_string($content)) {
            throw new EnvironmentException(
                "Something went wrong while reading {$this->nginxConf}, ensure the file is present."
            );
        }

        return $content;
    }

    /**
     * Get variable from the .env file.
     *
     * @param string $variable
     * @param string $localEnv
     *
     * @return string
     */
    private function getVariableValue(string $variable, string $localEnv): string
    {
        if (!$this->isVariableUsed($variable)) {
            return '';
        }

        preg_match("/{$variable}=(\\S+)/i", $localEnv, $match);
        if (empty($match)) {
            throw new EnvironmentException("{$variable} is undefined, ensure .env is correctly filled");
        }

        return (string) $match[1];
    }

    /**
     * Setup installation configuration.
     *
     * @param ComposerReader $composer
     *
     * @throws DockerHubException
     * @throws TransportExceptionInterface
     */
    private function prepareEnvironment(ComposerReader $composer): void
    {
        $this->output->section('Configuring docker environment');

        if (!$this->filesystem->exists($this->distEnv)) {
            $this->output->section('Creating docker local directory');
            $composer->runCommand('exec docker-local-install');
        }

        $configureEnv = !$this->filesystem->exists($this->localEnv)
            || $this->output->confirm(
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
     * Configure docker/local/.env.
     *
     * @throws DockerHubException
     * @throws TransportExceptionInterface
     */
    private function prepareDockerEnv(): void
    {
        if (!$this->filesystem->exists($this->distEnv)) {
            throw new EnvironmentException(
                'env.dist does not exist. Ensure emakinafr/docker-magento2 is present in dependencies.'
            );
        }
        $this->filesystem->copy($this->distEnv, $this->localEnv, true);

        $this->localEnvContent = $this->getLocalEnvData();

        $this->phpImage = $this->selectImage('php', 'DOCKER_PHP_IMAGE');
        $this->mysqlImage = $this->selectImage('magento2-mysql', 'DOCKER_MYSQL_IMAGE');
        $this->elasticsearchImage = $this->selectImage('magento2-elasticsearch', 'DOCKER_ELASTICSEARCH_IMAGE');

        $types = ['blackfire', 'mysql'];
        foreach ($types as $type) {
            if ($this->output->confirm('Do you want to configure <fg=yellow>' . ucfirst($type) . '</> ?')) {
                $this->configureEnv($type);
            }
        }

        $this->filesystem->dumpFile($this->localEnv, $this->localEnvContent);
    }

    /**
     * Retrieve tags available for the given image and configure the variable accordingly to the user's choice.
     *
     * @param string $imageName
     * @param string $variable
     *
     * @throws DockerHubException
     * @throws TransportExceptionInterface
     *
     * @return string
     */
    private function selectImage(string $imageName, string $variable): string
    {
        $availableTags = $this->dockerHub->getImageTags($imageName);

        if (\count($availableTags) > 1) {
            for ($i = \count($availableTags); $i > 0; --$i) {
                $availableTags[$i] = $availableTags[$i - 1];
            }
            unset($availableTags[0]); // Remove duplicate of first choice
            $image = (string) $this->output->choice(
                "Select the image you want to use for {$imageName} :",
                $availableTags,
                $availableTags[1]
            );
        } else {
            $image = $availableTags[0];
        }

        $value = "{$variable}={$image}";
        $replacement = preg_replace("/({$variable}=\\S*)/i", $value, $this->localEnvContent);
        if ($replacement === null) {
            throw new EnvironmentException("Error while configuring variable {$variable}.");
        }
        $this->localEnvContent = $replacement;

        return $image;
    }

    /**
     * Configure environment variables in the .env file for a specific type.
     *
     * @param string $type Section to configure
     */
    private function configureEnv(string $type): void
    {
        $regex = "/(^{$type}\\w+)=(\\w*)/im";
        preg_match_all($regex, $this->localEnvContent, $matches, PREG_SET_ORDER, 0);
        if (\count($matches)) {
            foreach ($matches as $match) {
                $conf = $this->output->ask($match[1], $match[2] ?? null);
                if ($conf !== '' && $match[2] !== $conf) {
                    $pattern = "/({$match[1]}=)(\\w*)/i";
                    $content = preg_replace($pattern, "$1{$conf}", $this->localEnvContent);
                    if (!\is_string($content)) {
                        throw new EnvironmentException('Error while configuring environment.');
                    }

                    $this->localEnvContent = $content;
                }
            }
        } else {
            $this->output->warning(
                "Type <fg=yellow>{$type}</> has no configuration, maybe it is not supported yet or there's nothing to configure."
            );
        }
    }

    /**
     * Add the host to /etc/hosts if missing
     * TODO Fix find a way to prevent permission error.
     *
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
            if ($this->output->confirm(
                'It seems like this host is not in your hosts file yet, do you want to add it ?'
            )) {
                $newHost = sprintf("# Added by %s\n", Kernel::NAME);
                $newHost .= "127.0.0.1   {$serverName}\n";
                $this->filesystem->appendToFile('/etc/hosts', $newHost);
                $this->output->text('Server added in your host file.');
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
        $serverName = $this->getServerName();
        if ($this->output->confirm(
            "The server name is currently <fg=yellow>{$serverName}</>, do you want to change it ?",
            false
        )) {
            $serverName = $this->output->ask(
                'Specify the server name',
                $serverName
            );
            $pattern = '/(server_name )(\\S+)/i';
            $content = preg_replace($pattern, "$1{$serverName};", $this->getNginxConf());
            if (!\is_string($content)) {
                throw new EnvironmentException('Error while preparing the nginx conf.');
            }

            $this->filesystem->dumpFile($this->nginxConf, $content);
        }

        return $serverName;
    }
}
