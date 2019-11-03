<?php

namespace Magphi\Helper;

use Magphi\Component\DockerCompose;
use Magphi\Component\Mutagen;
use Magphi\Component\Process;
use Magphi\Component\ProcessFactory;
use Magphi\Entity\Environment;
use Magphi\Exception\FileTooBig;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

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
        $this->environment->autoLocate();
    }

    public function setOutputInterface(OutputInterface $outputInterface): void
    {
        $this->outputInterface = $outputInterface;
    }

    public function checkSystemPrerequisites(): array
    {
        return [
            'Docker' => ['mandatory' => true, 'status' => $this->isInstalled('docker')],
            'Docker-Compose' => ['mandatory' => true, 'status' => $this->isInstalled('docker-compose')],
            'MySQL' => ['mandatory' => true, 'status' => $this->isInstalled('mysql')],
            'Mutagen' => [
                'mandatory' => true,
                'status' => $this->isInstalled('mutagen'),
                'comment' => '<fg=yellow>https://mutagen.io/</>',
            ],
            'Yarn' => ['mandatory' => false, 'status' => $this->isInstalled('yarn')],
            'Magento Cloud CLI' => [
                'mandatory' => false,
                'status' => $this->isInstalled('magento-cloud'),
                'comment' => 'Recommended when working on a Magento Cloud project.',
            ],
        ];
    }

    /**
     * @throws FileException
     * @throws FileNotFoundException
     * @throws FileTooBig
     */
    public function databaseImport(string $database, string $filename): Process
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        $coef = 1;
        $compressed = false;
        switch ($ext) {
            case 'zip':
                $command = ['unzip', '-p'];
                $compressed = true;

                break;
            case 'gz':
            case 'gzip':
                $command = ['gunzip', '-c'];
                $compressed = true;

                break;
            case 'sql':
            default:
                $command = ['cat'];

                break;
        }

        $command = array_merge(
            $command,
            [$filename, '|', 'mysql', '-h', '127.0.0.1', '-u', 'root', '-D', $database]
        );

        if ($compressed) {
            if (filesize($filename) > 100000000) {
                $command = implode(' ', $command);

                throw new FileTooBig(
                    "The file is too big to be automatically imported. Try to import it by yourself with the following command:\n {$command}"
                );
            }
            $coef = 2;
        }

        $lines = $this->countLines($filename) / $coef;

        $process = $this->processFactory->runProcessWithProgressBar(
            array_merge(
                $command,
                ['-v']
            ),
            3600,
            function (/* @noinspection PhpUnusedParameterInspection */ $type, $buffer) {
                preg_match_all('/^.(?:(?!-).)*$/m', $buffer, $matches);

                return \count($matches[0]);
            },
            $this->outputInterface,
            $lines,
            true
        );

        return $process;
    }

    /**
     * @throws ProcessTimedOutException
     */
    public function startMake(bool $install = false): Process
    {
        $process = $this->processFactory->runProcessWithProgressBar(
            ['make', 'start'],
            30,
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

    /**
     * @throws FileException
     * @throws FileNotFoundException
     */
    private function countLines(string $filename): int
    {
        $linecount = 0;
        $handle = fopen($filename, 'r');
        if ($handle === false) {
            throw new FileNotFoundException($filename.' not found.');
        }
        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line === false) {
                throw new FileException('Unexpected end of file.');
            }
            preg_match_all('/^.(?:(?!-).)*$/m', $line, $matches);
            $linecount += \count($matches[0]);
        }
        fclose($handle);

        return $linecount;
    }
}
