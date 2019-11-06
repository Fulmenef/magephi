<?php

namespace Magephi\Component;

use Magephi\Exception\GitException;

class Git
{
	/** @var ProcessFactory */
	private $processFactory;

	public function __construct(ProcessFactory $processFactory)
	{
		$this->processFactory = $processFactory;
	}

	/**
	 * Create the tag for the given version on the given branch.
	 *
	 * @param string $version
	 * @param string $branch If nothing if provided, it'll create the tag on master.
	 *
	 * @return bool
	 */
	public function createTag(string $version, string $branch = 'master'): bool
	{
		$command = ['git', 'tag', $version, $branch];
		$process = $this->processFactory->runProcess($command, 10, []);

		return $process->isSuccessful();
	}

	/**
	 * Find changes since last tag.
	 *
	 * @param string $branch If nothing if provided, it'll retrieve changes on master branch.
	 *
	 * @return string
	 */
	public function getChangelog(string $branch = 'master'): string
	{
		$process = $this->processFactory->runProcess(
			['git', 'log', "--pretty=format:'* %s (%h)'", "$(git describe --tags --abbrev=0)...HEAD", $branch],
			10,
			[],
			true
		);

		return $process->getOutput();
	}

	/**
	 * Checkout on the given branch.
	 *
	 * @param string $branch Checkout on master if no branch if provided.
	 *
	 * @return bool
	 * @throws GitException
	 */
	public function checkout(string $branch = 'master'): bool
	{
		$command = ['git', 'checkout', $branch];
		$process = $this->processFactory->runProcess($command, 10, []);

		if (!$process->isSuccessful()) {
			throw new GitException("Unable to checkout on branch $branch. Cause:\n {$process->getErrorOutput()}");
		}

		return true;
	}

	/**
	 * Add a file to staging area.
	 *
	 * @param string $file If no file provided, every unstaged file will be added.
	 *
	 * @return bool
	 */
	public function add(string $file = '.'): bool
	{
		$command = ['git', 'add', $file];
		$process = $this->processFactory->runProcess($command, 10, []);

		return $process->isSuccessful();
	}

	/**
	 * Commit staged files with the given message.
	 *
	 * @param string $message
	 *
	 * @return bool
	 */
	private function commit(string $message): bool
	{
		$command = ['git', 'commit', '-m', $message];
		$process = $this->processFactory->runProcess($command, 10, []);

		return $process->isSuccessful();
	}

	/**
	 * Commit the given version.
	 *
	 * @param string $version
	 *
	 * @return bool
	 */
	public function commitRelease(string $version): bool
	{
		return $this->commit("Release version $version");
	}

	/**
	 * Update current branch with latest changes.
	 *
	 * @return bool
	 * @throws GitException
	 */
	public function pull(): bool
	{
		$command = ['git', 'pull'];
		$process = $this->processFactory->runProcess($command, 10, []);

		if (!$process->isSuccessful()) {
			throw new GitException("Unable to update branch. Cause:\n {$process->getErrorOutput()}");
		}

		return true;
	}

	/**
	 * Push given branch to given remote.
	 *
	 * @param string $branch Branch to push. Master branch is used if none is provided.
	 * @param string $remote Remote to push branch to. Push to origin by default.
	 *
	 * @return bool
	 * @throws GitException
	 */
	public function push(string $branch = 'master', string $remote = 'origin'): bool
	{
		$command = ['git', 'push', $remote, $branch];
		$process = $this->processFactory->runProcess($command, 20, []);

		if (!$process->isSuccessful()) {
			throw new GitException("Unable to push branch $branch to $remote. Cause:\n {$process->getErrorOutput()}");
		}

		return true;
	}
}
