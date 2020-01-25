<?php

namespace Magephi\Command\Environment;

use Exception;
use InvalidArgumentException;
use Magephi\Command\AbstractCommand;
use Magephi\Component\DockerCompose;
use Magephi\Component\ProcessFactory;
use Magephi\Entity\Environment;
use Magephi\Helper\Database;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to import a database dump. The MySQL container must be started.
 */
class ImportCommand extends AbstractEnvironmentCommand
{
    protected string $command = 'import';

    protected Database $database;

    private Environment $environment;

    public function __construct(
        ProcessFactory $processFactory,
        DockerCompose $dockerCompose,
        Database $database,
        Environment $environment,
        string $name = null
    ) {
        parent::__construct($processFactory, $dockerCompose, $name);
        $this->database = $database;
        $this->environment = $environment;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrerequisites(): array
    {
        $prerequisites = parent::getPrerequisites();
        $prerequisites['binary'] = array_merge($prerequisites['binary'], ['Mysql']);

        return $prerequisites;
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Filename to the (possibly compressed) SQL file to import')
            ->addArgument(
                'database',
                InputArgument::OPTIONAL,
                "Destination database. If no database provided, the database defined in docker/local/.env will be used. If the database does not exist, it'll be created."
            )
            ->setDescription('Import a SQL file into a database inside the MySQL Container.')
            ->setHelp('This command allows you to import a SQL file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $this->convertToString($input->getArgument('file'));

        $database = $this->convertToString($input->getArgument('database'));
        if (empty($database)) {
            $database = $this->environment->getDatabase();
        }

        if ($database === '') {
            throw new InvalidArgumentException(
                "The database is not defined. Ensure a database is defined in {$this->environment->__get('localEnv')} or provide one in the command."
            );
        }

        try {
            $process = $this->database->import($database, $file);
        } catch (Exception $e) {
            $this->interactive->error($e->getMessage());

            return AbstractCommand::CODE_ERROR;
        }

        if (!$process->getProcess()->isSuccessful()) {
            $this->interactive->error($process->getProcess()->getErrorOutput());

            return AbstractCommand::CODE_ERROR;
        }

        $seconds = round($process->getDuration());

        $this->interactive->success(
            sprintf(
                'The dump has been imported in %s in %d minutes and %d seconds ',
                $database,
                $seconds / 60,
                $seconds % 60
            )
        );

        if ($this->interactive->confirm('Do you want to update the urls ?', true)) {
            try {
                $process = $this->database->updateUrls($database);
            } catch (Exception $e) {
                $this->interactive->error($e->getMessage());

                return AbstractCommand::CODE_ERROR;
            }

            if (!$process->getProcess()->isSuccessful()) {
                $this->interactive->error($process->getProcess()->getOutput());
                $this->interactive->error($process->getProcess()->getErrorOutput());

                return AbstractCommand::CODE_ERROR;
            }
            $this->interactive->success(
                'The urls has been updated.'
            );
        }

        return AbstractCommand::CODE_SUCCESS;
    }

    /**
     * @param mixed $string
     *
     * @throws InvalidTypeException
     *
     * @return string
     */
    private function convertToString($string): string
    {
        if (!\is_string($string)) {
            return '';
        }

        return $string;
    }
}
