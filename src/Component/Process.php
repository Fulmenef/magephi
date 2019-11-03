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
     * @return $this
     */
    public function createProgressBar(OutputInterface $output, ?int $max = null): self
    {
        $this->progressBar = new ProgressBar($output, $max ?: 0);

        return $this;
    }

    public function getProgressBar(): ?ProgressBar
    {
        return $this->progressBar;
    }

    public function setProgressCallback(callable $progressCallback): void
    {
        $this->progressCallback = $progressCallback;
    }

    public function getProgressCallback(): callable
    {
        return $this->progressCallback;
    }

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
     */
    public function getDuration(): float
    {
        return $this->endTime - $this->startTime;
    }
}
