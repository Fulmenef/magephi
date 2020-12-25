<?php

declare(strict_types=1);

namespace Magephi\Command;

use Exception;
use Magephi\Component\Git;
use Magephi\Component\ProcessFactory;
use Magephi\Exception\ComposerException;
use Magephi\Exception\GitException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * Command to create and release a new version.
 */
class ReleaseCommand extends Command
{
    private KernelInterface $kernel;

    private ProcessFactory $processFactory;

    private Git $git;

    private SymfonyStyle $interactive;

    private LoggerInterface $logger;

    public function __construct(
        KernelInterface $appKernel,
        ProcessFactory $processFactory,
        Git $git,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->kernel = $appKernel;
        $this->processFactory = $processFactory;
        $this->git = $git;
        $this->logger = $logger;
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

    protected function configure(): void
    {
        $this
            ->setName('release')
            ->setDescription('Release a new version of Magephi')
            ->addArgument(
                'version',
                InputArgument::REQUIRED,
                'Version to release, must follow MAJOR.MINOR.PATCH pattern'
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

        if (empty($this->git->getChangelog())
            && !$this->interactive->confirm(
                "There's no changes since last tag, are you sure you want to release a new tag ?",
                false
            )) {
            return 1;
        }
        $this->logger->debug('Changes found.');

        if (!$this->git->createTag($version)) {
            $this->interactive->error("A tag for version {$version} already exists");

            return AbstractCommand::CODE_ERROR;
        }
        $this->logger->debug('Tag created.');

        if ($this->gitPush($version)) {
            return AbstractCommand::CODE_ERROR;
        }
        $this->logger->debug('Tag pushed.');

        if ($this->gitPush()) {
            return AbstractCommand::CODE_ERROR;
        }
        $this->logger->debug('Master pushed.');

        $this->interactive->success(
            "Version {$version} has been pushed."
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
