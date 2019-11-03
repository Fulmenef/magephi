<?php

namespace Magphi\Component;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class Process
{
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

    public function __construct(
        array $command,
        ?float $timeout,
        array $env = null,
        bool $shell = false
    ) {
    	$this->process = new \Symfony\Component\Process\Process($command, null, $env, null, $timeout);
        if ($shell) {
            $this->process->setCommandLine(implode(' ', $command));
        }
    }

    /**
     * @param OutputInterface $output
     * @param null|int        $max
     *
     * @return $this
     */
    public function createProgressBar(OutputInterface $output, ?int $max = null): self
    {
        $this->progressBar = new ProgressBar($output, $max ?: 0);

        return $this;
    }

    /**
     * @return null|ProgressBar
     */
    public function getProgressBar(): ?ProgressBar
    {
        return $this->progressBar;
    }

    /**
     * @param callable $progressCallback
     */
    public function setProgressCallback(callable $progressCallback): void
    {
        $this->progressCallback = $progressCallback;
    }

    /**
     * @return callable
     */
    public function getProgressCallback(): callable
    {
        return $this->progressCallback;
    }

    /**
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
            $this->process->wait(
                function ($type, $buffer) use ($progressFunction) {
                    if ($steps = $progressFunction($type, $buffer)) {
                        $this->progressBar->advance(\is_int($steps) ? $steps : 1);
                    }
                }
            );

            if ( $this->process->isSuccessful()) {
                $this->progressBar->finish();
            }
            $this->endTime = microtime(true);
        }
    }

    /**
     * Return the duration of the process in seconds.
     *
     * @return float
     */
    public function getDuration(): float
    {
        return $this->endTime - $this->startTime;
    }

	/**
	 * @return \Symfony\Component\Process\Process
	 */
    public function getProcess(): \Symfony\Component\Process\Process
    {
    	return $this->process;
    }

	/**
	 * @return bool
	 */
    public function isSuccessful(): bool
    {
    	return $this->process->isSuccessful();
    }
}
