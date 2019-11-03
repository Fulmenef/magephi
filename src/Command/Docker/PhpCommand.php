<?php

namespace Magphi\Command\Docker;

class PhpCommand extends AbstractDockerCommand
{
    protected $service = 'php';

    protected $arguments = 'www-data:www-data';
}
