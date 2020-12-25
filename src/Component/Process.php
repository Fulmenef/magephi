<?php

declare(strict_types=1);

namespace Magephi\Component;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process as SymfonyProcess;

class Process
{
    public const CODE_TIMEOUT = 5;

    private SymfonyProcess $process;

    private ?ProgressBar $progressBar = null;

    /** @var callable */
    private $progressCallback;

    private float $startTime;

    private ?float $endTime = null;

    private ?int $exitCode;

    /**
     * ShellProcess constructor.
     *
     * @param string[]      $command
     * @param null|float    $timeout
     * @param null|string[] $env
     * @param bool          $shell
     */
    public function __construct(array $command, ?float $timeout, ?array $env = [], bool $shell = false)
    {
        if ($shell) {
            $command = implode(' ', $command);
            $this->process =
                SymfonyProcess::fromShellCommandline($command, null, $env, null, $timeout);
        } else {
            $this->process = new SymfonyProcess($command, null, $env, null, $timeout);
        }
    }

    /**
     * Create and associate a progress bar with the process.
     *
     * @param OutputInterface $output
     * @param null|int        $max
     *
     * @return Process
     */
    public function createProgressBar(OutputInterface $output, ?int $max = null): self
    {
        $this->progressBar = new ProgressBar($output, $max ?: 0);

        return $this;
    }

    /**
     * Return the progress bar if defined.
     *
     * @return null|ProgressBar
     */
    public function getProgressBar(): ?ProgressBar
    {
        return $this->progressBar;
    }

    /**
     * Set the callback used by the progress bar to advance.
     *
     * @param callable $progressCallback
     */
    public function setProgressCallback(callable $progressCallback): void
    {
        $this->progressCallback = $progressCallback;
    }

    /**
     * Return the callback used by the progress bar.
     *
     * @return callable
     */
    public function getProgressCallback(): callable
    {
        return $this->progressCallback;
    }

    /**
     * Return the duration of the process. Start time is initialized in the start function
     * If endtime is not defined we define it now.
     *
     * @return float
     */
    public function getDuration(): float
    {
        if ($this->endTime === null) {
            $this->endTime = microtime(true);
        }

        return $this->endTime - $this->startTime;
    }

    /**
     * Start the process. If a progress bar is set then we display it and use the callback to advance it during the
     * process.
     *
     * @param null|callable $callback
     * @param string[]      $env
     */
    public function start(callable $callback = null, array $env = []): void
    {
        $this->startTime = microtime(true);

        $this->process->start($callback, $env);
        if ($this->progressBar instanceof ProgressBar) {
            /** @var ProgressBar $progressBar */
            $progressBar = $this->progressBar;

            // Resume progress bar if current step is defined.
            if ($progressBar->getProgress()) {
                $progressBar->display();
            } else {
                $progressBar->start();
            }
            $progressFunction = $this->progressCallback;

            try {
                $this->process->wait(
                    function ($type, $buffer) use ($progressFunction, $progressBar) {
                        if ($steps = $progressFunction($type, $buffer)) {
                            $progressBar->advance(\is_int($steps) ? $steps : 1);
                        }
                    }
                );
            } catch (ProcessTimedOutException $e) {
                $progressBar->setMaxSteps($progressBar->getProgress());
                $this->process->addErrorOutput($e->getMessage());
                $progressBar->finish();
                $this->exitCode = self::CODE_TIMEOUT;
            }

            if ($this->process->isSuccessful()) {
                $progressBar->finish();
            }
            $this->endTime = microtime(true);
        }
    }

    /**
     * Call the wait function on the process.
     *
     * @param null|callable $callback function used to determined until when the process must wait
     *
     * @return int Exit code
     */
    public function wait(callable $callback = null): int
    {
        return $this->process->wait($callback);
    }

    /**
     * @return SymfonyProcess
     */
    public function getProcess(): SymfonyProcess
    {
        return $this->process;
    }

    /**
     * @return null|int
     */
    public function getExitCode(): ?int
    {
        return $this->exitCode ?? $this->process->getExitCode();
    }

    /**
     * Execute the run command of the process.
     *
     * @param null|callable $callback
     * @param string[]      $env
     *
     * @return int
     */
    public function run(callable $callback = null, array $env = []): int
    {
        $this->startTime = microtime(true);

        $var = $this->process->run($callback, $env);

        $this->endTime = microtime(true);

        return $var;
    }
}
