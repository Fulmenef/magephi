<?php

namespace Magephi\Command\Docker;

class PhpCommand extends AbstractDockerCommand
{
    protected string $service = 'php';

    protected string $arguments = 'www-data:www-data';
}
