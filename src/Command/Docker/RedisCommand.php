<?php

declare(strict_types=1);

namespace Magephi\Command\Docker;

class RedisCommand extends AbstractDockerCommand
{
    protected string $service = 'redis';
}
