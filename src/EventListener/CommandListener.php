<?php

namespace Magephi\EventListener;

use ArgumentCountError;
use Magephi\Command\AbstractCommand;
use Magephi\Entity\Environment;
use Magephi\Entity\System;
use Magephi\Exception\EnvironmentException;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

class CommandListener
{
    private System $system;

    private Environment $environment;

    public function __construct(System $system, Environment $environment)
    {
        $this->system = $system;
        $this->environment = $environment;
    }

    /**
     * Check if the prerequisites for the Magephi command are filled.
     *
     * @param ConsoleCommandEvent $event
     *
     * @return int
     */
    public function onConsoleCommand(ConsoleCommandEvent $event): int
    {
        // gets the command to be executed
        $command = $event->getCommand();
        /** @var AbstractCommand $command */
        if ($command instanceof AbstractCommand) {
            if ($command->getName() !== 'magephi:environment:create') {
                if ($this->environment->__get('dockerComposeFile') === null) {
                    throw new EnvironmentException(
                        'This command cannot be used here, install the environment with the `install` command or go inside a configured project directory.'
                    );
                }
            }
            $commandPrerequisites = $command->getPrerequisites();
            if (!empty($commandPrerequisites['binary'])) {
                $systemPrerequisites = $this->system->getBinaryPrerequisites();
                $this->checkUndefinedPrerequisites(
                    $commandPrerequisites['binary'],
                    $systemPrerequisites,
                    'binary'
                );
                foreach ($commandPrerequisites['binary'] as $prerequisite) {
                    if (!$systemPrerequisites[$prerequisite]['status']) {
                        throw new EnvironmentException(
                            sprintf('%s is necessary to use this command, please install it.', $prerequisite)
                        );
                    }
                }
            }
            if (!empty($commandPrerequisites['service'])) {
                $systemPrerequisites = $this->system->getServicesPrerequisites();
                $this->checkUndefinedPrerequisites(
                    $commandPrerequisites['service'],
                    $systemPrerequisites,
                    'service'
                );
                foreach ($commandPrerequisites['service'] as $prerequisite) {
                    if (!$systemPrerequisites[$prerequisite]['status']) {
                        throw new EnvironmentException(
                            sprintf(
                                '%s is not running, the environment must be started to use this command.',
                                $prerequisite
                            )
                        );
                    }
                }
            }
        }

        return AbstractCommand::CODE_SUCCESS;
    }

    /**
     * Check if the prerequisites given for the command correspond to prerequisites defined in the System class.
     * Throw an error if it doesn't.
     *
     * @param string[] $command
     * @param array[]  $system
     * @param string   $type
     *
     * @throws ArgumentCountError
     */
    private function checkUndefinedPrerequisites(array $command, array $system, string $type): void
    {
        if (!empty($diff = array_diff($command, array_keys($system)))) {
            throw new ArgumentCountError(
                sprintf(
                    'Undefined %s prerequisite(s) specified: %s',
                    $type,
                    implode(',', $diff)
                )
            );
        }
    }
}
