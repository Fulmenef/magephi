<?php

declare(strict_types=1);

namespace Magephi\Command\Docker;

class MysqlCommand extends AbstractDockerCommand
{
    protected string $service = 'mysql';
}
