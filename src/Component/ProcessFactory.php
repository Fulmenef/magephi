<?php

namespace Magphi\Component;

use Symfony\Component\Console\Output\OutputInterface;

class ProcessFactory
{
    /**
     * Create an instance of Process with the given command and return it.
     *
     * @param mixed $shell
     */
    public function createProcess(array $command, ?float $timeout = 3600.00, ?array $env = null, $shell = false): Process
    {
        return new Process($command, $timeout, $env, $shell);
    }

    public function runProcess(array $command, float $timeout = 3600.00): Process
    {
        $process = $this->createProcess($command, $timeout);
        $process->start();
        $process->wait();

        return $process;
    }

    /**
     * Create and start a process with an associated progress bar.
     *
     * @param callable $progressFunction Used to update the progress bar. Return true to advance by 1, return an int to advance the bar with the number of steps.
     */
    public function runProcessWithProgressBar(
        array $command,
        float $timeout,
        callable $progressFunction,
        OutputInterface $output,
        int $maxSteps = null,
        bool $shell = false
    ): Process {
        $process = $this->createProcess($command, $timeout, null, $shell);
        $process->createProgressBar($output, $maxSteps);
        $process->setProgressCallback($progressFunction);
        $process->start();

        return $process;
    }

    public function runInteractiveProcess(array $command, ?float $timeout = null, array $env = null)
    {
        $process = $this->createProcess($command, $timeout, $env);

        $process->setTty(Process::isTtySupported());
        $process->run(
            static function (string $type, string $buffer) {
                echo $buffer;
            }
        );
    }
}
