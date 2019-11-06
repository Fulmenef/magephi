<?php

namespace Magephi\Command;

use Exception;
use Github\Api\Repo;
use Github\Api\Repository\Releases;
use Github\Client;
use Github\Exception\MissingArgumentException;
use Github\Exception\RuntimeException;
use Magephi\Component\Git;
use Magephi\Component\ProcessFactory;
use Magephi\Exception\GitException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Command to create and release a new version.
 *
 * @package Magephi\Command
 */
class ReleaseCommand extends Command
{
	const USER_NAME = 'fulmenef';
	const REPO_NAME = 'magephi';
	const TOKEN_FILE = '.github_token';
	const DOC_BRANCH = 'gh-pages';
	const MANIFEST = 'manifest.json';

	/** @var KernelInterface */
	private $kernel;
	/** @var ProcessFactory */
	private $processFactory;
	/** @var Git */
	private $git;
	/** @var SymfonyStyle */
	private $interactive;

	public function __construct(
		KernelInterface $appKernel, ProcessFactory $processFactory, Git $git, string $name = null
	) {
		$this->kernel = $appKernel;
		$this->processFactory = $processFactory;
		$this->git = $git;
		parent::__construct($name);
	}

	protected function configure()
	{
		$this
			->setName('magephi:release')
			->setAliases(['release'])
			->setDescription('Release a new version of Magephi')
			->addArgument(
				'version',
				InputArgument::REQUIRED,
				'Version to release, must follow MAJOR.MINOR.PATCH pattern'
			)
			->addOption(
				'prod',
				null,
				InputOption::VALUE_NONE,
				'Use this option to create the release directly without draft.'
			)
			->addOption(
				'prerelease',
				null,
				InputOption::VALUE_NONE,
				'Use this option if the release is a pre-release.'
			);
	}

	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		$this->interactive = new SymfonyStyle($input, $output);
		parent::initialize($input, $output);
	}

	/**
	 * This command should not be enable on a prod environment.
	 *
	 * @return bool
	 */
	public function isEnabled()
	{
		return $this->kernel->getEnvironment() === 'dev';
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int|null
	 */
	protected function execute(InputInterface $input, OutputInterface $output): ?int
	{
		/** @var string $version */
		$version = $input->getArgument('version');
		try {
			$this->validateVersion($version);
			$token = $this->getToken();
		} catch (Exception $e) {
			$this->interactive->error($e->getMessage());

			return 1;
		}

		if (empty($changes = $this->git->getChangelog())
			&& !$this->interactive->confirm(
				"There's no changes since last tag, are you sure you want to release a new tag ?",
				false
			)) {
			return 1;
		}

		if (!$this->git->createTag($version)) {
			$this->interactive->error("A tag for version $version already exists");

			return 1;
		}

		$boxProcess = $this->processFactory->runProcess(['make', 'box'], 60);
		if (!$boxProcess->isSuccessful()) {
			$this->interactive->error($boxProcess->getErrorOutput());

			return 1;
		}

		$buildPath = 'build/magephi.phar';
		$sha1 = $this->processFactory->runProcess(['openssl', 'sha1', '-r', $buildPath]);
		$sha1 = explode(' ', $sha1->getOutput())[0];

		try {
			$this->git->checkout(self::DOC_BRANCH);
			$this->git->pull();
		} catch (GitException $e) {
			$this->interactive->error($e->getMessage());

			return 1;
		}

		$downloadPath = "downloads/magephi-${version}.phar";
		$this->processFactory->runProcess(['cp', $buildPath, $downloadPath], 10);
		$this->git->add($downloadPath);

		$data = [
			'name'    => 'magephi.phar',
			'sha1'    => $sha1,
			'date'    => (new \DateTime())->format('Y-m-d H:i:s'),
			'url'     => "https://fulmenef.github.io/magephi/$downloadPath",
			'version' => $version
		];
		$manifest = $this->addToManifest($data);
		$this->git->add($manifest);
		$this->git->commitRelease($version);

		try {
			$this->git->checkout();
			$this->git->push();
			$this->git->push(self::DOC_BRANCH);
		} catch (GitException $e) {
			$this->interactive->error($e->getMessage());

			return 1;
		}

		$client = new Client();
		$client->authenticate($token, null, Client::AUTH_HTTP_TOKEN);
		/** @var Repo $api */
		$api = $client->api('repo');
		/** @var Releases $releases */
		$releases = $api->releases();

		try {
			$response = $releases->create(
				self::USER_NAME,
				self::REPO_NAME,
				[
					"tag_name"         => $version,
					"target_commitish" => "master",
					"name"             => $version,
					"body"             => $changes,
					"draft"            => !$input->getOption('prod'),
					"prerelease"       => $input->getOption('prerelease')
				]
			);
		} catch (RuntimeException|MissingArgumentException $e) {
			$this->interactive->error($e->getMessage());

			return 1;
		}

		$this->interactive->success("Version $version has been release ! You can see it here: {$response['html_url']}");

		return null;
	}

	/**
	 * Check if the token file exists, if not throw an exception, else return the token inside the file
	 *
	 * @return string Token inside the file
	 *
	 * @throws Exception
	 */
	private function getToken(): string
	{
		$tokenFile = $this->findFile(self::TOKEN_FILE);
		$token = $tokenFile->getContents();

		if (empty($token)) {
			throw new Exception('Token cannot be empty');
		}

		return $token;
	}

	/**
	 * @param array $data
	 *
	 * @return string
	 */
	public function addToManifest(array $data): string
	{
		$fileInfo = $this->findFile(self::MANIFEST);

		$content = $fileInfo->getContents();
		$content = json_decode($content, true);
		$content[] = $data;
		/** @var string $content */
		$content = json_encode($content);

		$fs = new Filesystem();
		$fs->dumpFile($fileInfo->getRelativePathname(), $content);
		return $fileInfo->getRelativePathname();
	}

	/**
	 * Run a regex against the given version to ensure the format is correct
	 *
	 * @param string $tag
	 *
	 * @throws Exception
	 */
	private function validateVersion(string $tag): void
	{
		$re = '/(\d+\.\d+\.\d+)/m';
		preg_match($re, $tag, $match);
		if (empty($match) || $match[0] !== $tag) {
			throw new Exception("Version $tag is not correct, format is MAJOR.MINOR.PATCH. eg: 1.2.3");
		}
	}

	/**
	 * @param string $filename
	 * @param string $directory
	 *
	 * @return SplFileInfo
	 * @throws FileNotFoundException
	 */
	private function findFile(string $filename, string $directory = ''): SplFileInfo
	{
		if ($directory === '') {
			$directory = $this->kernel->getProjectDir();
		}
		$finder = new Finder();
		$finder->files()->ignoreDotFiles(false)->in($directory)->name($filename);
		if (!$finder->hasResults()) {
			throw new FileNotFoundException("File $filename is missing");
		}
		$iterator = $finder->getIterator();
		$iterator->rewind();
		/** @var SplFileInfo $file */
		$file = $iterator->current();

		return $file;
	}
}
