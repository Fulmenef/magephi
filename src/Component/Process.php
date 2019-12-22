<?php

namespace Magephi\Component;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class Process
{
    const CODE_TIMEOUT = 2;

    /** @var \Symfony\Component\Process\Process */
    private $process;

    /** @var ProgressBar */
    private $progressBar;

    /** @var callable */
    private $progressCallback;

    /** @var float */
    private $startTime;

    /** @var float */
    private $endTime;

    /** @var int|null */
    private $exitCode;

    /**
     * ShellProcess constructor.
     *
     * @param string|string[] $command
     * @param null|float      $timeout
     * @param null|string[]   $env
     * @param bool            $shell
     */
    public function __construct($command, ?float $timeout, ?array $env = [], bool $shell = false)
    {
        if ($shell) {
            if (\is_array($command)) {
                $command = implode(' ', $command);
            }
            $this->process =
                \Symfony\Component\Process\Process::fromShellCommandline($command, null, $env, null, $timeout);
        } else {
            $this->process = new \Symfony\Component\Process\Process($command, null, $env, null, $timeout);
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
     * @return callable
     */
    public function getProgressCallback(): callable
    {
        return $this->progressCallback;
    }

    /**
     * Return the duration of the process. Start time is initialized in the start function
     * If endtime is not defined we define it now.
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
     * @param array         $env
     */
    public function start(callable $callback = null, array $env = []): void
    {
        $this->startTime = microtime(true);
        $this->process->start($callback, $env);
        if ($this->progressBar instanceof ProgressBar) {
            // Resume progress bar if current step is defined.
            if ($this->progressBar->getProgress()) {
                $this->progressBar->display();
            } else {
                $this->progressBar->start();
            }
            $progressFunction = $this->progressCallback;
            try {
                $this->process->wait(
                    function ($type, $buffer) use ($progressFunction) {
                        if ($steps = $progressFunction($type, $buffer)) {
                            $this->progressBar->advance(\is_int($steps) ? $steps : 1);
                        }
                    }
                );
            } catch (ProcessTimedOutException $e) {
                $this->progressBar->setMaxSteps($this->progressBar->getProgress());
                $this->process->addErrorOutput($e->getMessage());
                $this->progressBar->finish();
                $this->exitCode = self::CODE_TIMEOUT;
            }

            if ($this->process->isSuccessful()) {
                $this->progressBar->finish();
            }
            $this->endTime = microtime(true);
        }
    }

    /**
     * Call the wait function on the process.
     *
     * @param callable|null $callback Function used to determined until when the process must wait.
     *
     * @return int Exit code
     */
    public function wait(callable $callback = null): int
    {
        return $this->process->wait($callback);
    }

    /**
     * @return \Symfony\Component\Process\Process
     */
    public function getProcess(): \Symfony\Component\Process\Process
    {
        return $this->process;
    }

    /**
     * @return int|null
     */
    public function getExitCode()
    {
        return $this->exitCode ?? $this->process->getExitCode();
    }
}
