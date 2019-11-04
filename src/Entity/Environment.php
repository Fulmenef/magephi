<?php

namespace Magephi\Entity;

use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class Environment
{
    /** @var string */
    private $dockerComposeFile;
    /** @var string */
    private $dockerComposeContent;
    /** @var int */
    private $containers;
    /** @var int */
    private $volumes;
    /** @var string */
    private $phpDockerfile;
    /** @var string */
    private $phpImage;
    /** @var string */
    private $localEnv;
    /** @var string */
    private $distEnv;
    /** @var string */
    private $nginxConf;
    /** @var string */
    private $currentDir;
    /** @var string */
    private $magentoApp;

    public function __get($name)
    {
        return $this->{$name};
    }

    public function __set($name, $value)
    {
        return $this->{$name} = $value;
    }

    /**
     * Try to locate automatically the environment files.
     * If a file is missing or has multiple occurrence, the user will have to fill the blanks.
     */
    public function autoLocate()
    {
        $files = [
            'docker-compose.yml' => ['pattern' => '*/*/*/docker-compose.yml', 'variable' => 'dockerComposeFile'],
            'Dockerfile' => ['pattern' => '*/*/*/php/Dockerfile', 'variable' => 'phpDockerfile'],
            '.env' => ['pattern' => '*/*/.env', 'variable' => 'localEnv'],
            '.env.dist' => ['pattern' => '*/*/.env.dist', 'variable' => 'distEnv'],
            'nginx' => ['pattern' => '*/*/nginx.conf', 'variable' => 'nginxConf'],
            '.magento.app.yaml' => ['pattern' => '.magento.app.yaml', 'variable' => 'magentoApp'],
        ];

        foreach ($files as $file => $data) {
            $retrievedFiles = $this->retrieveFile($data['pattern'], $file);
            if ($retrievedFiles[$file] !== null && !\is_array($retrievedFiles[$file])) {
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
                throw new FileException($this->localEnv.' not found.');
            }

            preg_match('/DOCKER_PHP_IMAGE=(\w+)/i', $localEnv, $match);
            $this->phpImage = $match[1];
        }
    }

    /**
     * Search files matching the given pattern from the current directory.
     */
    public function retrieveFile(string $pattern, string $key): array
    {
        $files = glob($pattern);
        if ($files === false) {
            throw new FileNotFoundException('Error while searching files for pattern '.$pattern);
        }
        switch (\count($files)) {
            case 0:
                $val = null;

                break;
            case 1:
                $val = $files[0];

                break;
            default:
                $val = $files;

                break;
        }

        return [$key => $val];
    }

    /**
     * Retrieves environment variables required to run processes.
     */
    public function getDockerRequiredVariables(): array
    {
        return [
            'COMPOSE_FILE' => './'.$this->dockerComposeFile,
            'COMPOSE_PROJECT_NAME' => 'magento2_'.$this->currentDir,
            'DOCKER_PHP_IMAGE' => $this->phpImage,
            'PROJECT_LOCATION' => getcwd(),
        ];
    }

    /**
     * Retrieve default database defined in the local .env file.
     * Return an empty string if database not found or undefined.
     */
    public function getDefaultDatabase(): string
    {
        if ($this->localEnv) {
            $content = file_get_contents($this->localEnv);
            if (!\is_string($content)) {
                throw new FileNotFoundException($this->localEnv.' not found.');
            }

            preg_match('/MYSQL_DATABASE=(\w+)/', $content, $match);

            return isset($match[1]) ? $match[1] : '';
        }

        return '';
    }

	/**
	 * Return number of containers defined in the docker-compose.yml
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
	 * Return the content of the docker-compose.yml
	 *
	 * @return string
	 */
    public function getDockerComposeContent(): string
    {
        if (\is_string($this->dockerComposeContent)) {
            return $this->dockerComposeContent;
        }

        $content = file_get_contents($this->dockerComposeFile);
        if ($content === false) {
            throw new FileException('docker-compose.yml is not found.');
        }
        $this->dockerComposeContent = $content;

        return $this->dockerComposeContent;
    }

	/**
	 * Return the number of volumes defined in docker-compose.yml
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
}
