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
	 * @param string|array $command
	 * @param float|null $timeout
	 * @param array|null $env
	 */
	public function __construct($command, ?float $timeout, ?array $env = [])
	{
		if (is_array($command)) {
			$command = implode(' ', $command);
		}
		$this->process = Process::fromShellCommandline($command, null, $env, null, $timeout);
	}

	/**
	 * @inheritDoc
	 */
	public function createProgressBar(OutputInterface $output, ?int $max = null): self
	{
		$this->progressBar = new ProgressBar($output, $max ?: 0);

		return $this;
	}

	/**
	 * @inheritDoc
	 */
	public function getProgressBar(): ?ProgressBar
	{
		return $this->progressBar;
	}

	/**
	 * @inheritDoc
	 */
	public function setProgressCallback(callable $progressCallback): void
	{
		$this->progressCallback = $progressCallback;
	}

	/**
	 * @inheritDoc
	 */
	public function getProgressCallback(): callable
	{
		return $this->progressCallback;
	}

	/**
	 * @inheritDoc
	 */
	public function getDuration(): float
	{
		if ($this->endTime === null) {
			$this->endTime = microtime(true);
		}

		return $this->endTime - $this->startTime;
	}

	/**
	 * @inheritDoc
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
	 * @inheritDoc
	 */
	public function wait(callable $callback = null)
	{
		return $this->process->wait($callback);
	}

	/**
	 * @inheritDoc
	 */
	public function isSuccessful(callable $callback = null)
	{
		return $this->process->isSuccessful();
	}

	/**
	 * @inheritDoc
	 */
	public function getOutput()
	{
		return $this->process->getOutput();
	}

	/**
	 * @inheritDoc
	 */
	public function getErrorOutput()
	{
		$this->process->getErrorOutput();
	}

	/**
	 * @inheritDoc
	 */
	public function run(callable $callback = null, array $env = []): int
	{
		return $this->process->run($callback, $env);
	}

	/**
	 * @inheritDoc
	 */
	public function setTty(bool $tty)
	{
		return $this->process->setTty($tty);
	}
}