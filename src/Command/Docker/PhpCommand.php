<?php

declare(strict_types=1);

namespace Magephi\Command\Docker;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PhpCommand extends AbstractDockerCommand
{
    public const ARGUMENT_WWW_DATA = 'www-data:www-data';

    private const ARGUMENT_ROOT = 'root:root';

    private const OPTION_ROOT = 'root';

    protected string $service = 'php';

    protected array $arguments = ['user' => self::ARGUMENT_WWW_DATA];

    protected function configure(): void
    {
        parent::configure();
        $this->addOption(
            self::OPTION_ROOT,
            null,
            InputOption::VALUE_NONE,
            'Use this option to connect to the container as root instead of www-data'
        );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        if ($input->getOption(self::OPTION_ROOT)) {
            $this->arguments['user'] = self::ARGUMENT_ROOT;
        }
    }
}
