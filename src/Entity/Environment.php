<?php

namespace Magephi\Entity;

use Magephi\Exception\EnvironmentException;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

class Environment
{
    private ?string $dockerComposeFile = null;

    private ?string $dockerComposeContent = null;

    private ?int $containers = null;

    private ?int $volumes = null;

    private string $phpDockerfile;

    private string $phpImage;

    private ?string $localEnv = null;

    private ?string $localEnvContent = null;

    private string $distEnv;

    private string $nginxConf;

    private string $currentDir;

    private string $magentoApp;

    private ?string $magentoEnv = null;

    public function __construct()
    {
        $this->autoLocate();
    }

    /**
     * @param string $name
     *
     * @return null|int|string
     */
    public function __get(string $name)
    {
        return $this->{$name};
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return Environment
     */
    public function __set(string $name, $value): self
    {
        $this->{$name} = $value;

        return $this;
    }

    /**
     * Try to locate automatically the environment files.
     * TODO: If a file is missing or has multiple occurrence, the user will have to fill the blanks.
     */
    public function autoLocate(): void
    {
        $files = [
            'docker-compose.yml' => ['pattern' => '*/*/*/docker-compose.yml', 'variable' => 'dockerComposeFile'],
            'Dockerfile'         => ['pattern' => '*/*/*/php/Dockerfile', 'variable' => 'phpDockerfile'],
            '.env'               => ['pattern' => '*/*/.env', 'variable' => 'localEnv'],
            '.env.dist'          => ['pattern' => '*/*/.env.dist', 'variable' => 'distEnv'],
            'nginx'              => ['pattern' => '*/*/nginx.conf', 'variable' => 'nginxConf'],
            '.magento.app.yaml'  => ['pattern' => '.magento.app.yaml', 'variable' => 'magentoApp'],
            'env.php'            => ['pattern' => '*/*/env.php', 'variable' => 'magentoEnv'],
        ];

        foreach ($files as $file => $data) {
            $retrievedFiles = $this->retrieveFile($data['pattern'], $file);
            if (!empty($retrievedFiles[$file]) && !\is_array($retrievedFiles[$file])) {
                $this->{$files[$file]['variable']} = $retrievedFiles[$file];
            }
        }

        $currentDir = getcwd();
        if (!\is_string($currentDir)) {
            throw new DirectoryNotFoundException('Current dir is not found.');
        }
        $this->currentDir = basename($currentDir);

        if ($this->localEnv) {
            $localEnv = file_get_contents($this->localEnv);
            if (!\is_string($localEnv)) {
                throw new FileNotFoundException($this->localEnv . ' not found.');
            }

            preg_match('/DOCKER_PHP_IMAGE=(\w+)/i', $localEnv, $match);
            if (empty($match)) {
                throw new EnvironmentException('PHP image is undefined, ensure .env is correctly filled');
            }
            $this->phpImage = $match[1];
        }
    }

    /**
     * Search files matching the given pattern from the current directory.
     *
     * @param string $pattern
     * @param string $key
     *
     * @return string[]
     */
    public function retrieveFile(string $pattern, string $key): array
    {
        /** @var false|string[] $files */
        $files = glob($pattern);
        if ($files === false) {
            throw new FileNotFoundException('Error while searching files for pattern ' . $pattern);
        }

        switch (\count($files)) {
            case 0:
                $val = '';

                break;

            default:
                $val = $files[0];

                break;
        }

        return [$key => $val];
    }

    /**
     * Retrieves environment variables required to run processes.
     *
     * @return string[]
     */
    public function getDockerRequiredVariables(): array
    {
        return [
            'COMPOSE_FILE'         => './' . $this->dockerComposeFile,
            'COMPOSE_PROJECT_NAME' => 'magento2_' . $this->currentDir,
            'DOCKER_PHP_IMAGE'     => $this->phpImage,
            'PROJECT_LOCATION'     => getcwd() ?: '',
        ];
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
        if (!\is_string($this->localEnvContent)) {
            if ($this->localEnv) {
                $content = file_get_contents($this->localEnv);
                if (!\is_string($content)) {
                    throw new FileNotFoundException($this->localEnv . ' empty.');
                }
                $this->localEnvContent = $content;
            } else {
                new EnvironmentException('Local .env is not defined, please install the environment first.');
            }
        }

        return $this->localEnvContent ?: '';
    }

    /**
     * Get parameter in the .env file.
     *
     * @param string $name Parameter name to get
     *
     * @return string
     */
    public function getEnvData(string $name): string
    {
        $name = strtoupper($name);
        preg_match("/{$name}=(\\w+)/", $this->getLocalEnvData(), $match);

        return isset($match[1]) ? $match[1] : '';
    }

    /**
     * Retrieve default database defined in the local .env file.
     * Return an empty string if database not found or undefined.
     */
    public function getDatabase(): string
    {
        return $this->getEnvData('mysql_database');
    }

    /**
     * Return number of containers defined in the docker-compose.yml.
     *
     * @return int
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

    /**
     * Return the number of volumes defined in docker-compose.yml.
     *
     * @return int
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
     * Return true if app/etc/env.php exists.
     *
     * @return bool
     */
    public function hasMagentoEnv(): bool
    {
        return null !== $this->magentoEnv;
    }

    /**
     * Get configured server name.
     *
     * @param bool $complete Whether or not we want the prefix with the protocol
     *
     * @return string
     */
    public function getServerName($complete = false): string
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
        preg_match_all('/server_name (\S*);/m', $content, $matches, PREG_SET_ORDER, 0);

        $prefix = '';
        if ($complete) {
            $prefix = 'https://www.';
        }

        return $prefix . $matches[0][1];
    }
}
