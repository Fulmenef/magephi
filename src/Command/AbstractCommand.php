<?php

namespace Magephi\Command;

use Herrera\Phar\Update\Manager;
use Herrera\Phar\Update\Manifest;
use Herrera\Phar\Update\Update;
use Herrera\Version\Parser;
use Herrera\Version\Version;
use Magephi\Application;
use Magephi\Component\DockerCompose;
use Magephi\Component\ProcessFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractCommand extends Command
{
	const CODE_SUCCESS = 0;
	const CODE_ERROR = 1;

	const MANIFEST_FILE = 'https://fulmenef.github.io/magephi/manifest.json';

	/** @var SymfonyStyle */
	protected $interactive;

	/** @var ProcessFactory */
	protected $processFactory;

	/** @var DockerCompose */
	protected $dockerCompose;

	public function __construct(ProcessFactory $processFactory, DockerCompose $dockerCompose, string $name = null)
	{
		$this->processFactory = $processFactory;
		$this->dockerCompose = $dockerCompose;
		parent::__construct($name);
	}

	/**
	 * Check if there's a new version available.
	 *
	 * @return null|string Return latest new version or null if nothing is available
	 */
	public function checkNewVersionAvailable(): ?string
	{
		/** @var Application $app */
		$app = $this->getApplication();
		$version = $app->getVersion();

		if (substr($version, -3, 3) === 'dev') {
			return null;
		}

		$version = Parser::toVersion($version);
		$manager = new Manager(Manifest::loadFile(self::MANIFEST_FILE));
		/** @var null|Update $update */
		$update = $manager->getManifest()->findRecent($version, true, true);

		return $update !== null ? $this->rebuildVersion($update->getVersion()) : $update;
	}

	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		$this->interactive = new SymfonyStyle($input, $output);
		parent::initialize($input, $output);

		$update = $this->checkNewVersionAvailable();
		if ($update !== null) {
			$this->interactive->warning(
				"A new version is available, use the update command to update to version {$update}"
			);
		}
	}

	/**
	 * Checks a condition, outputs a message, and exits if failed.
	 *
	 * @param string $success the success message
	 * @param string $failure the failure message
	 * @param callable $condition the condition to check
	 * @param bool $exit whether to exit on failure
	 */
	protected function check($success, $failure, $condition, $exit = true)
	{
		if ($condition()) {
			$this->interactive->writeln("<fg=green>  [*] {$success}</>");
		} elseif (!$exit) {
			$this->interactive->writeln("<fg=yellow>  [!] {$failure}</>");
		} else {
			$this->interactive->writeln("<fg=red>  [X] {$failure}</>");
			exit(1);
		}
	}

	/**
	 * Rebuild version into one piece.
	 *
	 * @param Version $version
	 *
	 * @return string Version in 1.2.3 form.
	 */
	protected function rebuildVersion(Version $version): string
	{
		return sprintf('%d.%d.%d', $version->getMajor(), $version->getMinor(), $version->getPatch());
	}
}
