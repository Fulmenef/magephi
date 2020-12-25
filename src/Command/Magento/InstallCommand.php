<?php

declare(strict_types=1);

namespace Magephi\Command\Magento;

use Magephi\Command\AbstractCommand;
use Magephi\Component\Process;
use Magephi\Exception\EnvironmentException;
use Magephi\Exception\ProcessException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InstallCommand extends AbstractMagentoCommand
{
    protected string $command = 'install';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Install the Magento2 project in the current directory.')
            ->setHelp('This command allows you to install the Magento 2 project in the current directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $environment = $this->manager->getEnvironment();

        //TODO Add elasticsearch to match Magento 2.4
        $command = sprintf(
            "bin/magento setup:install
            --base-url=%s
            --db-host=%s
            --db-name=%s
            --db-user=%s
            --db-password='%s'
            --backend-frontname=%s
            --admin-firstname=%s
            --admin-lastname=%s
            --admin-email=%s
            --admin-user=%s
            --admin-password=%s
            --language=%s
            --currency=%s
            --timezone=%s
            --use-rewrites=%d",
            $environment->getServerName(true),
            'mysql',
            $environment->getDatabase(),
            $environment->getEnvData('mysql_user'),
            $environment->getEnvData('mysql_password'),
            $this->interactive->ask('What must be the backend frontname ?', 'admin'),
            $this->interactive->ask('What is the admin firstname ?', 'admin'),
            $this->interactive->ask('What is the admin lastname ?', 'admin'),
            $this->interactive->ask('What is the admin email ?', 'admin@admin.com'),
            $this->interactive->ask('What is the admin username ?', 'admin'),
            $this->interactive->ask(
                'What is the admin password ?',
                '4dM7NPwd',
                function ($answer) {
                    if (empty($answer)) {
                        throw new \RuntimeException(
                            'The password cannot be empty.'
                        );
                    }
                    if (\strlen($answer) < 7) {
                        throw new \RuntimeException(
                            'The password must be at least 7 characters.'
                        );
                    }
                    if (\strlen(preg_replace('![^A-Z]+!', '', $answer)) < 1) {
                        throw new \RuntimeException(
                            'The password must include uppercase characters.'
                        );
                    }
                    if (\strlen(preg_replace('![^0-9]+!', '', $answer)) < 2) {
                        throw new \RuntimeException(
                            'The password must include numerical characters.'
                        );
                    }

                    return $answer;
                }
            ),
            $this->interactive->ask('What is the project default language ?', 'en_US'),
            $this->interactive->ask('What is the project default currency ?', 'USD'),
            $this->interactive->ask('What is the project default timezone ?', 'America/Chicago'),
            $this->interactive->confirm('Do you want to use url rewrites ?', true) ? 1 : 0
        );

        try {
            $this->dockerCompose->executeContainerCommand('php', 'mkdir pub/static');
            $this->dockerCompose->executeContainerCommand('php', 'composer dumpautoload');
            $this->dockerCompose->executeContainerCommand('php', 'rm -rf generated');
            $this->interactive->section('Installation');

            if ($_ENV['SHELL_VERBOSITY'] >= 1) {
                $install = $this->dockerCompose->executeContainerCommand('php', $command);
            } else {
                /** @var Process $install */
                $install = $this->dockerCompose->executeContainerCommand('php', $command, true);
                $progressBar = new ProgressBar($output, 0);
                $regex = '/Progress: (\d*) \/ (\d*)/i';
                $install->start();
                $progressBar->start();
                $install->getProcess()->waitUntil(
                    function (string $type, string $buffer) use ($regex, $progressBar) {
                        preg_match($regex, $buffer, $match);
                        if (isset($match[1])) {
                            if ($progressBar->getMaxSteps() !== $match[2]) {
                                $progressBar->setMaxSteps($match[2]);
                            }
                            $progressBar->setProgress($match[1]);
                        }

                        return false;
                    }
                );
                if ($install->getProcess()->isSuccessful()) {
                    $progressBar->finish();
                }
            }
        } catch (EnvironmentException | ProcessException $e) {
            $this->interactive->error($e->getMessage());

            return AbstractCommand::CODE_ERROR;
        }

        $this->interactive->newLine(2);

        if (!$install->getProcess()->isSuccessful()) {
            $this->interactive->error('An error occurred during installation');
            $error = explode(PHP_EOL, $install->getProcess()->getErrorOutput());

            // Clean error to have a smaller one
            for ($i = 0; $i < 5; ++$i) {
                array_pop($error);
            }

            $this->interactive->error(implode(PHP_EOL, $error));

            return AbstractCommand::CODE_ERROR;
        }

        $this->interactive->success(
            sprintf(
                'Magento installed, you can access to your website with the url %s',
                $environment->getServerName(true)
            )
        );

        return AbstractCommand::CODE_SUCCESS;
    }
}
