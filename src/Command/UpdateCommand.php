<?php

namespace Magephi\Command;

use Herrera\Phar\Update\Manager;
use Herrera\Phar\Update\Manifest;
use Magephi\Application;
use Magephi\Kernel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to upgrade the application to the latest version.
 *
 * @package Magephi\Command
 */
class UpdateCommand extends Command
{
	const MANIFEST_FILE = 'https://fulmenef.github.io/magephi/manifest.json';

	/** @var SymfonyStyle */
	private $interactive;

	protected function configure()
	{
		$this
			->setName('magphi:update')
			->setAliases(['update'])
			->setDescription('Updates Magphi to the latest version');
	}

	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		$this->interactive = new SymfonyStyle($input, $output);
		parent::initialize($input, $output);
	}

	/**
	 * TODO: Ask before update and display new version at the end
	 * TODO: Double check when a major version is released and the user accept to upgrade
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int|void|null
	 * @throws \Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$app = $this->getApplication();
		if ($app instanceof Application) {
			$manager = new Manager(Manifest::loadFile(self::MANIFEST_FILE));
			$manager->update($app->getVersion(), true);

			$this->interactive->success(sprintf("%s has been upgraded to the latest version", Kernel::NAME));
		} else {
			throw new \Exception(
				sprintf('Application must be type of %s, %s found.', Application::class, \gettype($app))
			);
		}
	}
}
