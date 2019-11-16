<?php

namespace Magephi\Component;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class Process extends \Symfony\Component\Process\Process
{
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
        parent::__construct($command, null, $env, null, $timeout);
        if ($shell) {
            $this->setCommandLine(implode(' ', $command));
        }
    }

    /**
     * Create and associate a progress bar to the process.
     *
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
     * Get the progress bar of the process. Null if none has been associated.
     *
     * @return null|ProgressBar
     */
    public function getProgressBar(): ?ProgressBar
    {
        return $this->progressBar;
    }

    /**
     * Set the callback function used to advance the progress bar.
     *
     * @param callable $progressCallback
     */
    public function setProgressCallback(callable $progressCallback): void
    {
        $this->progressCallback = $progressCallback;
    }

    /**
     * Get the callback function used to advance the progress bar.
     *
     * @return callable
     */
    public function getProgressCallback(): callable
    {
        return $this->progressCallback;
    }

    /**
     * Override the default start function. If a progress bar is associated, we use the defined callback to advance it.
     * If the return value of the callback is an int, we advance the progress from that number bar of steps.
     *
     * @param null|callable $callback
     * @param array         $env
     */
    public function start(callable $callback = null, array $env = []): void
    {
        $this->startTime = microtime(true);
        parent::start($callback, $env);
        if ($this->progressBar instanceof ProgressBar) {
            // Resume progress bar if current step is defined.
            if ($this->progressBar->getProgress()) {
                $this->progressBar->display();
            } else {
                $this->progressBar->start();
            }
            $progressFunction = $this->progressCallback;
            $this->wait(
                function ($type, $buffer) use ($progressFunction) {
                    if ($steps = $progressFunction($type, $buffer)) {
                        $this->progressBar->advance(\is_int($steps) ? $steps : 1);
                    }
                }
            );

            if ($this->isSuccessful()) {
                $this->progressBar->finish();
            }
            $this->endTime = microtime(true);
        }
    }

    /**
     * Return the duration of the process in seconds.
     * endTime may not have been initialized, so we set it at that time.
     */
    public function getDuration(): float
    {
        if ($this->endTime === null) {
            $this->endTime = microtime(true);
        }

        return $this->endTime - $this->startTime;
    }
}
