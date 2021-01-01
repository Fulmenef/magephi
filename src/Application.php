<?php

declare(strict_types=1);

namespace Magephi;

use Symfony\Bundle\FrameworkBundle\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\HttpKernel\KernelInterface;

class Application extends SymfonyApplication
{
    public function __construct(KernelInterface $kernel)
    {
        parent::__construct($kernel);
        BaseApplication::__construct(Kernel::NAME, Kernel::getVersion());
    }
}
