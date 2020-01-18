<?php

namespace Magephi\Helper;

use Magephi\Kernel;

class ReleaseHandler
{
    /**
     * @var Kernel
     */
    private Kernel $kernel;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * Remove previous version caches and logs.
     */
    public function handle(): void
    {
        $customDir = $this->kernel->getCustomDir();

        /** @var string[] $scan */
        $scan = scandir($customDir);

        /** @var string[] $diff */
        $diff = array_diff($scan, ['.', '..', $this->kernel->getVersion()]);
        if (!empty($diff)) {
            foreach ($diff as $directory) {
                $this->deleteFiles($customDir . '/' . $directory);
            }
        }
    }

    /**
     * Method to remove file and directory recursively.
     *
     * @param string $target
     */
    public function deleteFiles(string $target): void
    {
        if (is_dir($target)) {
            /** @var string[] $files */
            $files = glob($target . '*', GLOB_MARK); //GLOB_MARK adds a slash to directories returned

            foreach ($files as $file) {
                $this->deleteFiles($file);
            }

            rmdir($target);
        } elseif (is_file($target)) {
            unlink($target);
        }
    }
}
