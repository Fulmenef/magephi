<?php

declare(strict_types=1);

namespace Magephi\Command\Environment;

use Magephi\Component\DockerCompose;
use Magephi\Component\ProcessFactory;
use Magephi\Entity\Environment\Manager;
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

    public function __construct(
        ProcessFactory $processFactory,
        DockerCompose $dockerCompose,
        Manager $manager,
        Database $database
    ) {
        parent::__construct($processFactory, $dockerCompose, $manager);
        $this->database = $database;
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

        return $this->manager->importDatabase($file, $database) ? self::SUCCESS : self::FAILURE;
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
