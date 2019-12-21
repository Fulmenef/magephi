<?php

namespace Magephi\Component;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class ShellProcess implements ProcessInterface
{
    /** @var Process */
    private $process;

    /** @var ProgressBar */
    private $progressBar;

    /** @var callable */
    private $progressCallback;

    /** @var float */
    private $startTime;

    /** @var float */
    private $endTime;

    /**
     * ShellProcess constructor.
     *
     * @param string|string[] $command
     * @param null|float      $timeout
     * @param null|string[]   $env
     */
    public function __construct($command, ?float $timeout, ?array $env = [])
    {
        if (\is_array($command)) {
            $command = implode(' ', $command);
        }
        $this->process = Process::fromShellCommandline($command, null, $env, null, $timeout);
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
     */
    public function getDuration(): float
    {
        if ($this->endTime === null) {
            $this->endTime = microtime(true);
        }

        return $this->endTime - $this->startTime;
    }

    /**
     * {@inheritdoc}
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
    public function wait(callable $callback = null)
    {
        return $this->process->wait($callback);
    }

    /**
     * {@inheritdoc}
     */
    public function isSuccessful(callable $callback = null)
    {
        return $this->process->isSuccessful();
    }

    /**
     * {@inheritdoc}
     */
    public function getOutput()
    {
        return $this->process->getOutput();
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorOutput()
    {
        $this->process->getErrorOutput();
    }

    /**
     * {@inheritdoc}
     */
    public function run(callable $callback = null, array $env = []): int
    {
        return $this->process->run($callback, $env);
    }

    /**
     * {@inheritdoc}
     */
    public function setTty(bool $tty)
    {
        return $this->process->setTty($tty);
    }
}
