<?php

namespace Magephi\Component;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class Process extends \Symfony\Component\Process\Process implements ProcessInterface
{
    /** @var ProgressBar */
    private $progressBar;

    /** @var callable */
    private $progressCallback;

    /** @var float */
    private $startTime;

    /** @var float */
    private $endTime;

    /**
     * Process constructor.
     *
     * @param array      $command
     * @param null|float $timeout
     * @param null|array $env
     */
    public function __construct(
        array $command,
        ?float $timeout,
        array $env = null
    ) {
        parent::__construct($command, null, $env, null, $timeout);
    }

    /**
     * {@inheritdoc}
     */
    public function createProgressBar(OutputInterface $output, ?int $max = null): self
    {
        $this->progressBar = new ProgressBar($output, $max ?: 0);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getProgressBar(): ?ProgressBar
    {
        return $this->progressBar;
    }

    /**
     * {@inheritdoc}
     */
    public function setProgressCallback(callable $progressCallback): void
    {
        $this->progressCallback = $progressCallback;
    }

    /**
     * {@inheritdoc}
     */
    public function getProgressCallback(): callable
    {
        return $this->progressCallback;
    }

    /**
     * {@inheritdoc}
     * Override of the default start method.
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
     * {@inheritdoc}
     */
    public function getDuration(): float
    {
        if ($this->endTime === null) {
            $this->endTime = microtime(true);
        }

        return $this->endTime - $this->startTime;
    }
}
