<?php

namespace Magephi\Entity;

class System
{
    /** @var array[] */
    private array $binaries = [
        'Docker'            => ['mandatory' => true, 'check' => 'docker'],
        'Docker-Compose'    => ['mandatory' => true, 'check' => 'docker-compose'],
        'Mutagen'           => [
            'mandatory' => true,
            'check'     => 'mutagen',
            'comment'   => '<fg=yellow>https://mutagen.io/</>',
        ],
        'Yarn'              => ['mandatory' => false, 'check' => 'yarn'],
        'Magento Cloud CLI' => [
            'mandatory' => false,
            'check'     => 'magento-cloud',
            'comment'   => 'Recommended when working on a Magento Cloud project.',
        ],
        'Pipe Viewer'       => [
            'mandatory' => false,
            'check'     => 'pv',
            'comment'   => 'Necessary to display progress during database import.',
        ],
    ];

    /** @var array[] */
    private array $services = [
        'Docker'  => ['mandatory' => true, 'check' => 'docker info > /dev/null 2>&1'],
        'Mutagen' => ['mandatory' => true, 'check' => 'pgrep -f "mutagen"'],
    ];

    /**
     * Get all prerequisites.
     *
     * @return array[]
     */
    public function getAllPrerequisites(): array
    {
        $binaries = $this->getBinaryPrerequisites();
        $services = $this->getServicesPrerequisites();

        return ['binaries' => $binaries, 'services' => $services];
    }

    /**
     * Return the mandatory prerequisite no matter if it's a binary or service.
     *
     * @return array[]
     */
    public function getMandatoryPrerequisites(): array
    {
        $allPrerequisites = $this->getAllPrerequisites();
        $systemPrerequisites = [];
        foreach ($allPrerequisites as $type => $prerequisites) {
            $filtered = array_filter(
                $prerequisites,
                function ($array) {
                    return $array['mandatory'];
                }
            );
            if (!empty($filtered)) {
                $systemPrerequisites[$type] = $filtered;
            }
        }

        return $systemPrerequisites;
    }

    /**
     * Return the optional prerequisite no matter if it's a binary or service.
     *
     * @return array[]
     */
    public function getOptionalPrerequisites(): array
    {
        $allPrerequisites = $this->getAllPrerequisites();
        $systemPrerequisites = [];
        foreach ($allPrerequisites as $type => $prerequisites) {
            $filtered = array_filter(
                $prerequisites,
                function ($array) {
                    return !$array['mandatory'];
                }
            );
            if (!empty($filtered)) {
                $systemPrerequisites[$type] = $filtered;
            }
        }

        return $systemPrerequisites;
    }

    /**
     * Return the binary prerequisites, replace the `check` entry by the `status` determined by the return value of
     * the check.
     *
     * @return array[]
     */
    public function getBinaryPrerequisites(): array
    {
        $binaries = [];
        foreach ($this->binaries as $name => $binary) {
            $binaries[$name] = $this->getBinaryStatus($binary);
        }

        return $binaries;
    }

    /**
     * Return the service prerequisites, replace the `check` entry by the `status` determined by the return value of
     * the check.
     *
     * @return array[]
     */
    public function getServicesPrerequisites(): array
    {
        $services = [];
        foreach ($this->services as $name => $service) {
            $services[$name] = $this->getServiceStatus($service);
        }

        return $services;
    }

    /**
     * Check if the givne binary is installed.
     *
     * @param string $binary
     *
     * @return bool Return true if the binary is installed
     */
    public function isInstalled(string $binary): bool
    {
        if (\defined('PHP_WINDOWS_VERSION_BUILD')) {
            $command = "where {$binary}";
        } else {
            $command = "command -v {$binary}";
        }

        return $this->test($command);
    }

    /**
     * @param string[] $binary
     *
     * @return array<bool|string>
     */
    protected function getBinaryStatus(array $binary): array
    {
        $binary['status'] = $this->isInstalled($binary['check']);
        unset($binary['check']);

        return $binary;
    }

    /**
     * @param string[] $service
     *
     * @return array<bool|string>
     */
    protected function getServiceStatus(array $service): array
    {
        $service['status'] = $this->test($service['check']);
        unset($service['check']);

        return $service;
    }

    /**
     * Execute the shell command, return true if everything went fine.
     *
     * @param string $check
     *
     * @return bool
     */
    private function test(string $check): bool
    {
        exec($check, $output, $returnVar);

        return $returnVar === 0;
    }
}
