<?php

namespace Magephi\Command;

use Exception;
use Github\Api\Repo;
use Github\Api\Repository\Releases;
use Github\Client;
use Github\Exception\ErrorException;
use Github\Exception\MissingArgumentException;
use Github\Exception\RuntimeException;
use Magephi\Component\Git;
use Magephi\Component\ProcessFactory;
use Magephi\Exception\ComposerException;
use Magephi\Exception\GitException;
use Nadar\PhpComposerReader\ComposerReader;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * Command to create and release a new version.
 */
class ReleaseCommand extends Command
{
    public const USER_NAME = 'fulmenef';
    public const REPO_NAME = 'magephi';
    public const DOC_BRANCH = 'gh-pages';
    public const MANIFEST = 'manifest.json';

    /** @var KernelInterface */
    private $kernel;
    /** @var ProcessFactory */
    private $processFactory;
    /** @var Git */
    private $git;
    /** @var SymfonyStyle */
    private $interactive;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        KernelInterface $appKernel,
        ProcessFactory $processFactory,
        Git $git,
        LoggerInterface $logger,
        string $name = null
    ) {
        $this->kernel = $appKernel;
        $this->processFactory = $processFactory;
        $this->git = $git;
        $this->logger = $logger;
        parent::__construct($name);
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
     * Add release content to manifest.json.
     *
     * @param string[] $data
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
        $content = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $fs = new Filesystem();
        $fs->dumpFile($fileInfo->getRelativePathname(), $content);

        return $fileInfo->getRelativePathname();
    }

    protected function configure(): void
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

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->interactive = new SymfonyStyle($input, $output);
        parent::initialize($input, $output);
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $version */
        $version = $input->getArgument('version');

        try {
            $this->validateVersion($version);
        } catch (Exception $e) {
            $this->interactive->error($e->getMessage());

            return 1;
        }
        $this->logger->debug('Version is OK.');

        if (empty($changes = $this->git->getChangelog())
            && !$this->interactive->confirm(
                "There's no changes since last tag, are you sure you want to release a new tag ?",
                false
            )) {
            return 1;
        }
        $this->logger->debug('Changes found.');

        try {
            $files = ['composer.json', 'package.json'];

            foreach ($files as $file) {
                $this->updateJsonFile($version, $file);
                $this->git->add($file);
            }

            $this->git->commitRelease($version);
        } catch (Exception $e) {
            $this->interactive->error($e->getMessage());

            return AbstractCommand::CODE_ERROR;
        }

        if (!$this->git->createTag($version)) {
            $this->interactive->error("A tag for version {$version} already exists");

            return AbstractCommand::CODE_ERROR;
        }
        $this->logger->debug('Tag created.');

        $boxProcess = $this->processFactory->runProcess(['make', 'box'], 60);
        if (!$boxProcess->getProcess()->isSuccessful()) {
            $this->interactive->error($boxProcess->getProcess()->getErrorOutput());

            return AbstractCommand::CODE_ERROR;
        }
        $this->logger->debug('Phar application created.');

        $filename = 'magephi.phar';
        $buildPath = 'build/' . $filename;
        $sha1 = $this->processFactory->runProcess(['openssl', 'sha1', '-r', $buildPath]);
        $sha1 = explode(' ', $sha1->getProcess()->getOutput())[0];
        $this->logger->debug("Sha1: {$sha1}");

        try {
            $this->git->checkout(self::DOC_BRANCH);
            $this->git->pull();
            $this->logger->debug('Pull changes and references on doc branch.');
        } catch (GitException $e) {
            $this->interactive->error($e->getMessage());

            return AbstractCommand::CODE_ERROR;
        }

        $downloadPath = "downloads/magephi-{$version}.phar";
        $this->processFactory->runProcess(['cp', $buildPath, $downloadPath], 10);
        $this->processFactory->runProcess(['cp', '-f', $buildPath, $filename], 10);
        $this->git->add($downloadPath);
        $this->git->add($filename);
        $this->logger->debug('Phar added to git.');

        $data = [
            'name'    => $filename,
            'sha1'    => $sha1,
            'url'     => "https://fulmenef.github.io/magephi/{$downloadPath}",
            'version' => $version,
        ];
        $manifest = $this->addToManifest($data);
        $this->git->add($manifest);
        $this->git->commitRelease($version);
        $this->logger->debug('Info added to manifest.json');

        try {
            $this->git->checkout();
        } catch (GitException $e) {
            $this->interactive->error($e->getMessage());

            return AbstractCommand::CODE_ERROR;
        }
        if ($this->gitPush()) {
            return AbstractCommand::CODE_ERROR;
        }
        $this->logger->debug('Master pushed.');

        if ($this->gitPush(self::DOC_BRANCH)) {
            return AbstractCommand::CODE_ERROR;
        }
        $this->logger->debug('Doc pushed.');

        $dotenv = new Dotenv();
        $dotenv->load($this->kernel->getProjectDir() . '/.env.local');
        $client = new Client();
        $client->authenticate($_ENV['GITHUB_SECRET'], null, $_ENV['GITHUB_AUTH_METHOD']);
        $this->logger->debug('Authenticated on github.');
        /** @var Repo $api */
        $api = $client->api('repo');
        /** @var Releases $releases */
        $releases = $api->releases();

        try {
            $response = $releases->create(
                self::USER_NAME,
                self::REPO_NAME,
                [
                    'tag_name'         => $version,
                    'target_commitish' => 'master',
                    'name'             => $version,
                    'body'             => $changes,
                    'draft'            => !$input->getOption('prod'),
                    'prerelease'       => $input->getOption('prerelease'),
                ]
            );
            $this->logger->debug('Release created.');

            /** @var string $content */
            $content = file_get_contents($buildPath);
            $releases->assets()->create(
                self::USER_NAME,
                self::REPO_NAME,
                $response['id'],
                $filename,
                'application/zip',
                $content
            );
            $this->logger->debug('Asset created.');
        } catch (RuntimeException | MissingArgumentException | ErrorException $e) {
            $this->interactive->error($e->getMessage());

            return AbstractCommand::CODE_ERROR;
        }

        $this->interactive->success(
            "Version {$version} has been release ! You can see it here: {$response['html_url']}"
        );

        return AbstractCommand::CODE_SUCCESS;
    }

    /**
     * Run a regex against the given version to ensure the format is correct.
     *
     * @param string $tag
     */
    private function validateVersion(string $tag): void
    {
        $re = '/(\d+\.\d+\.\d+)/m';
        preg_match($re, $tag, $match);
        if (empty($match) || $match[0] !== $tag) {
            throw new ComposerException("Version {$tag} is not correct, format is MAJOR.MINOR.PATCH. eg: 1.2.3");
        }
    }

    /**
     * @param string $filename
     * @param string $directory
     *
     * @throws FileNotFoundException
     *
     * @return SplFileInfo
     */
    private function findFile(string $filename, string $directory = ''): SplFileInfo
    {
        if ($directory === '') {
            $directory = $this->kernel->getProjectDir();
        }
        $finder = new Finder();
        $finder->files()->ignoreDotFiles(false)->in($directory)->name($filename);
        if (!$finder->hasResults()) {
            throw new FileNotFoundException("File {$filename} is missing");
        }
        $iterator = $finder->getIterator();
        $iterator->rewind();

        return $iterator->current();
    }

    /**
     * Update json files (composer.json, package.json...) with the new version.
     * The field must be named "version".
     *
     * @param string $version
     * @param string $file
     */
    private function updateJsonFile(string $version, string $file): void
    {
        /** @var ComposerReader $json */
        $json = new ComposerReader($file);
        if (!$json->canRead()) {
            throw new ComposerException('Unable to read json.');
        }
        $json->updateSection('version', $version);
        $json->save();
    }

    /**
     * @param string $branch
     *
     * @return int
     */
    private function gitPush(string $branch = 'master'): int
    {
        try {
            $this->git->push($branch);
        } catch (GitException $e) {
            $this->interactive->error($e->getMessage());

            return AbstractCommand::CODE_ERROR;
        } catch (ProcessTimedOutException $e) {
            $this->interactive->note($e->getMessage());
        }

        return AbstractCommand::CODE_SUCCESS;
    }
}
