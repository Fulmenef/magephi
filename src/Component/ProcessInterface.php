<?php

namespace Magephi\Component;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

interface ProcessInterface
{
    /**
     * Method to start the process.
     * If a progress bar is associated, we use the defined callback to advance it.
     * If the return value of the callback is an int, we advance the progress from that number bar of steps.
     *
     * @param null|callable $callback
     * @param string[]      $env
     */
    public function start(callable $callback = null, array $env = []): void;

    /**
     * @param null|callable $callback
     *
     * @see Process::wait()
     */
    public function wait(callable $callback = null);

    /**
     * @param callable $callback
     *
     * @see Process::waitUntil()
     */
    public function waitUntil(callable $callback);

    /**
     * @param null|callable $callback
     * @param string[]      $env
     *
     * @return int
     *
     * @see Process::run()
     */
    public function run(callable $callback = null, array $env = []): int;

    /**
     * @param bool $tty
     *
     * @see Process::setTty()
     */
    public function setTty(bool $tty);

    /**
     * @see Process::isSuccessful()
     */
    public function isSuccessful();

    /**
     * @see Process::getOutput()
     */
    public function getOutput();

    /**
     * @see Process::getErrorOutput()
     */
    public function getErrorOutput();

    /**
     * Set the callback function used to advance the progress bar.
     *
     * @param callable $progressCallback
     */
    public function setProgressCallback(callable $progressCallback): void;

    /**
     * Get the callback function used to advance the progress bar.
     *
     * @return callable
     */
    public function getProgressCallback(): callable;

    /**
     * Get the progress bar of the process. Null if none has been associated.
     *
     * @return null|ProgressBar
     */
    public function getProgressBar(): ?ProgressBar;

    /**
     * Create and associate a progress bar to the process.
     *
     * @param OutputInterface $output
     * @param null|int        $max
     *
     * @return ProcessInterface
     */
    public function createProgressBar(OutputInterface $output, ?int $max = null): self;

    /**
     * Return the duration of the process in seconds.
     * endTime may not have been initialized, so we set it at that time.
     */
    public function getDuration(): float;
}
