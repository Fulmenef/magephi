<?php

declare(strict_types=1);

namespace Magephi\Command;

use Magephi\Component\DockerCompose;
use Magephi\Component\ProcessFactory;
use Magephi\Helper\UpdateHandler;
use Magephi\Kernel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to upgrade the application to the latest version.
 */
class UpdateCommand extends AbstractCommand
{
    private UpdateHandler $updateHandler;

    public function __construct(ProcessFactory $processFactory, DockerCompose $dockerCompose, UpdateHandler $updateHandler)
    {
        $this->updateHandler = $updateHandler;
        parent::__construct($processFactory, $dockerCompose);
    }

    public function getPrerequisites(): array
    {
        return [];
    }

    protected function configure(): void
    {
        $this
            ->setName('update')
            ->setDescription('Updates Magephi to the latest version');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->interactive = new SymfonyStyle($input, $output);
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $update = $this->checkNewVersionAvailable();

        if ($update !== null && Kernel::getMode() !== 'dev') {
            if ($result = $this->updateHandler->handle()) {
                $this->interactive->success(
                    sprintf(
                        '%s has been upgraded to %s.',
                        Kernel::NAME,
                        $result
                    )
                );

                exit(AbstractCommand::CODE_SUCCESS);    // Necessary to bypass Symfony post command check  and avoid errors
            }

            $this->interactive->error('Update canceled, something happened.');

            return AbstractCommand::CODE_ERROR;
        }

        $this->interactive->note('No new version to download');

        return AbstractCommand::CODE_SUCCESS;
    }
}
