<?php

namespace Magephi;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\HttpKernel\KernelInterface;

class Application extends \Symfony\Bundle\FrameworkBundle\Console\Application
{
    public function __construct(KernelInterface $kernel)
    {
        parent::__construct($kernel);
        BaseApplication::__construct(Kernel::NAME, Kernel::VERSION);
    }
}
