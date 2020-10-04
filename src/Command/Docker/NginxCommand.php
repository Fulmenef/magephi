<?php

declare(strict_types=1);

namespace Magephi\Command\Docker;

class NginxCommand extends AbstractDockerCommand
{
    protected string $service = 'nginx';
}
