<?php

declare(strict_types=1);

namespace Magephi\Entity\Environment;

use Symfony\Component\Console\Style\SymfonyStyle;

interface EnvironmentInterface
{
    /**
     * Environments may be required to render text in the output.
     *
     * @param SymfonyStyle $output
     *
     * @return EnvironmentInterface
     */
    public function setOutput(SymfonyStyle $output): self;

    /**
     * Get type id.
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Build environment.
     *
     * @return bool
     */
    public function build(): bool;

    /**
     * Stop environment.
     *
     * @return bool
     */
    public function stop(): bool;

    /**
     * Start environment.
     *
     * @return bool
     */
    public function start(): bool;

    /**
     * Install environment.
     *
     * @param array<mixed> $data
     *
     * @return bool
     */
    public function install(array $data = []): bool;

    /**
     * Uninstall environment.
     *
     * @return bool
     */
    public function uninstall(): bool;

    /**
     * Return number of containers used in the environment.
     *
     * @return int
     */
    public function getContainers(): int;

    /**
     * Return the number of volumes used in the environment.
     *
     * @return int
     */
    public function getVolumes(): int;

    /**
     * Retrieves environment variables required to run processes.
     *
     * @return string[]
     */
    public function getDockerRequiredVariables(): array;

    /**
     * Get files to backup when sharing the environment.
     *
     * @return string[]
     */
    public function getBackupFiles(): array;

    /**
     * Get parameter in the .env file.
     *
     * @param string $name Parameter name to get
     *
     * @return string
     */
    public function getEnvData(string $name): string;

    /**
     * Get configured server name.
     *
     * @param bool $complete Whether or not we want the prefix with the protocol
     *
     * @return string
     */
    public function getServerName($complete = false): string;

    /**
     * Retrieve default database defined in the magento env.php or local .env file as fallback
     * Return an empty string if database not found or undefined.
     */
    public function getDatabase(): string;

    /**
     * Return true if app/etc/env.php exists.
     * TODO Put this in abstract or Manager ?
     *
     * @return bool
     */
    public function hasMagentoEnv(): bool;

    /**
     * Get working directory.
     *
     * @return string
     */
    public function getWorkingDir(): string;
}
