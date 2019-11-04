<?php

namespace Magephi\Component;

use Symfony\Component\Console\Output\OutputInterface;

class ProcessFactory
{
	/**
	 * Create an instance of Process with the given command and return it.
	 *
	 * @param array $command
	 * @param float|null $timeout
	 * @param array|null $env Environment variables
	 * @param bool $shell Specify if the process should execute the command in shell directly
	 *
	 * @return Process
	 */
	public function createProcess(array $command, ?float $timeout = 3600.00, ?array $env = null, bool $shell = false
	): Process {
		return new Process($command, $timeout, $env, $shell);
	}

	/**
	 * Run a process directly without any customization
	 *
	 * @param array $command
	 * @param float $timeout
	 * @param array $env Environment variables
	 *
	 * @return Process
	 */
	public function runProcess(array $command, float $timeout = 3600.00, array $env = []): Process
	{
		$process = $this->createProcess($command, $timeout, $env);
		$process->start();
		$process->wait();

		return $process;
	}

	/**
	 * Create and start a process with an associated progress bar.
	 *
	 * @param array $command
	 * @param float $timeout
	 * @param callable $progressFunction Used to update the progress bar. Return true to advance by 1, return an int to advance the bar with the number of steps.
	 * @param OutputInterface $output
	 * @param int|null $maxSteps
	 * @param bool $shell Specify if the process should execute the command in shell directly
	 *
	 * @return Process
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

	/**
	 * Run a process and provide an interactive interface
	 *
	 * @param array $command
	 * @param float|null $timeout
	 * @param array|null $env Environment variables
	 */
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
