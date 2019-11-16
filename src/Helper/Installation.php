<?php

namespace Magephi\Helper;

use Magephi\Component\DockerCompose;
use Magephi\Component\Mutagen;
use Magephi\Component\Process;
use Magephi\Component\ProcessFactory;
use Magephi\Entity\Environment;
use Symfony\Component\Console\Output\OutputInterface;

class Installation
{
    /** @var ProcessFactory */
    private $processFactory;
    /** @var DockerCompose */
    private $dockerCompose;
    /** @var Mutagen */
    private $mutagen;
    /** @var Environment */
    private $environment;

    /** @var OutputInterface */
    private $outputInterface;

    public function __construct(DockerCompose $dockerCompose, ProcessFactory $processFactory, Mutagen $mutagen)
    {
        $this->dockerCompose = $dockerCompose;
        $this->processFactory = $processFactory;
        $this->mutagen = $mutagen;
        $this->environment = new Environment();
    }

    public function setOutputInterface(OutputInterface $outputInterface): void
    {
        $this->outputInterface = $outputInterface;
    }

    /**
     * For each prerequisite, check if the binary is installed.
     */
    public function checkSystemPrerequisites(): array
    {
        return [
            'Docker'            => ['mandatory' => true, 'status' => $this->isInstalled('docker')],
            'Docker-Compose'    => ['mandatory' => true, 'status' => $this->isInstalled('docker-compose')],
            'MySQL'             => ['mandatory' => true, 'status' => $this->isInstalled('mysql')],
            'Mutagen'           => [
                'mandatory' => true,
                'status'    => $this->isInstalled('mutagen'),
                'comment'   => '<fg=yellow>https://mutagen.io/</>',
            ],
            'Yarn'              => ['mandatory' => false, 'status' => $this->isInstalled('yarn')],
            'Magento Cloud CLI' => [
                'mandatory' => false,
                'status'    => $this->isInstalled('magento-cloud'),
                'comment'   => 'Recommended when working on a Magento Cloud project.',
            ],
        ];
    }

    /**
     * Import a database dump. Display a progress bar to visually follow the process.
     * In case of compression, the number of steps is not precise.
     *
     * @param string $database
     * @param string $filename
     *
     * @return Process
     */
    public function databaseImport(string $database, string $filename): Process
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        switch ($ext) {
            case 'zip':
                $command = ['bsdtar', '-xOf-'];

                break;
            case 'gz':
            case 'gzip':
                $command = ['gunzip', '-cd'];

                break;
            case 'sql':
            default:
                $command = [];

                break;
        }

        $command = array_merge(
            ['pv', '-ptefab', $filename, '|'],
            !empty($command) ? array_merge($command, ['|']) : $command,
            ['mysql', '-h', '127.0.0.1', '-u', 'root', '-D', $database]
        );

        return $this->processFactory->runProcessWithOutput(
            $command,
            3600,
            null,
            true
        );
    }

    /**
     * Run the `make start` command with a progress bar.
     *
     * @param bool $install
     *
     * @return Process
     */
    public function startMake(bool $install = false): Process
    {
        $process = $this->processFactory->runProcessWithProgressBar(
            ['make', 'start'],
            60,
            function (/* @noinspection PhpUnusedParameterInspection */ $type, $buffer) {
                return (strpos($buffer, 'Creating') !== false
                        && (
                            strpos($buffer, 'network')
                            || strpos($buffer, 'volume')
                            || strpos($buffer, 'done')
                        ))
                    || (strpos($buffer, 'Starting') && strpos($buffer, 'done'));
            },
            $this->outputInterface,
            $install ? $this->environment->getContainers() + $this->environment->getVolumes()
                + 2 : $this->environment->getContainers() + 1
        );

        return $process;
    }

    /**
     * Run the `make build` command with a progress bar.
     *
     * @return Process
     */
    public function buildMake(): Process
    {
        $process = $this->processFactory->runProcessWithProgressBar(
            ['make', 'build'],
            600,
            function (/* @noinspection PhpUnusedParameterInspection */ $type, $buffer) {
                return strpos($buffer, 'skipping') || strpos($buffer, 'tagged');
            },
            $this->outputInterface,
            $this->environment->getContainers()
        );

        return $process;
    }

    /**
     * Start or resume the mutagen session.
     *
     * @throws \Exception
     */
    public function startMutagen(): bool
    {
        if ($this->mutagen->isExistingSession()) {
            if ($this->mutagen->isPaused()) {
                $this->mutagen->resumeSession();
            }
        } else {
            $process = $this->mutagen->createSession();
            if (!$process->isSuccessful()) {
                throw new \Exception('Mutagen session could not be created');
            }
        }

        return true;
    }

    /**
     * Check if the given binary is installed.
     *
     * @param string $binary
     *
     * @return bool
     */
    private function isInstalled(string $binary): bool
    {
        if (\defined('PHP_WINDOWS_VERSION_BUILD')) {
            $command = "where {$binary}";
        } else {
            $command = "command -v {$binary}";
        }
        exec($command, $output, $return_var);

        return $return_var === 0;
    }
}
