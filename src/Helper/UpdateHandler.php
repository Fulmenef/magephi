<?php

declare(strict_types=1);

namespace Magephi\Helper;

use Humbug\SelfUpdate\Strategy\GithubStrategy;
use Humbug\SelfUpdate\Updater;
use Magephi\Kernel;

class UpdateHandler
{
    public const FILE_NAME = 'magephi.phar';

    public const PACKAGE_NAME = 'fulmenef/magephi';

    /**
     * Update application, return new version if successful. Rollback if there's an issue.
     *
     * @return null|string
     */
    public function handle(): ?string
    {
        $updater = new Updater(null, false);
        $strategy = new GithubStrategy();
        $strategy->setPackageName(self::PACKAGE_NAME);
        $strategy->setPharName(self::FILE_NAME);
        $strategy->setCurrentLocalVersion(Kernel::getVersion());
        $updater->setStrategyObject($strategy);

        $result = $updater->update();

        if (!$result) {
            $updater->rollback();
        }

        return $result ? $updater->getNewVersion() : null;
    }
}
