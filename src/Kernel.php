<?php

namespace Magephi;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public const NAME = 'Magephi';

    public const VERSION = '@package_version@';

    public const MODE = '@mode@';

    private const CONFIG_EXTS = '.{php,xml,yaml,yml}';

    public function registerBundles(): iterable
    {
        $contents = require $this->getProjectDir() . '/config/bundles.php';
        foreach ($contents as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }

    /**
     * Retrieves the custom directory located in the HOME directory of the current user.
     *
     * @throws InvalidConfigurationException
     */
    public static function getCustomDir(): string
    {
        $home = PHP_OS_FAMILY !== 'Windows' ? getenv('HOME') : $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];

        if (\is_string($home) && $home !== '') {
            $home = rtrim($home, \DIRECTORY_SEPARATOR);
        } else {
            throw new InvalidConfigurationException('Unable to determine the home directory.');
        }

        return "{$home}/.magephi";
    }

    /**
     * Retrieves the custom directory for the current version.
     */
    public function getVersionDir(): string
    {
        return $this->getCustomDir() . '/' . self::VERSION;
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }

    /**
     * Checks whether the application is currently run as a PHAR.
     */
    public function isArchiveContext(): bool
    {
        return strpos($this->getProjectDir(), 'phar://') !== false;
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidConfigurationException
     */
    public function getCacheDir()
    {
        return $this->isArchiveContext() ? $this->getVersionDir() . '/cache' : parent::getCacheDir();
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidConfigurationException
     */
    public function getLogDir()
    {
        return $this->isArchiveContext() ? $this->getVersionDir() . '/log' : parent::getLogDir();
    }

    /**
     * Return the current version if the application is in prod mode
     * Return a tag followed by -dev if in development mode.
     *
     * @return string
     */
    public static function getVersion(): string
    {
        if (self::getMode() === 'dev') {
            return exec('git -C ' . \dirname(__DIR__) . ' describe --tags') . '-dev';
        }

        return self::VERSION;
    }

    /**
     * Return current mode of the application.
     *
     * @return string
     */
    public static function getMode(): string
    {
        $mode = self::MODE;
        if ($mode !== 'prod') {
            $mode = 'dev';
        }

        return $mode;
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->addResource(new FileResource($this->getProjectDir() . '/config/bundles.php'));
        $container->setParameter('container.dumper.inline_class_loader', true);
        $confDir = $this->getProjectDir() . '/config';

        $loader->load($confDir . '/{packages}/*' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{packages}/' . $this->environment . '/**/*' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{services}' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{services}_' . $this->environment . self::CONFIG_EXTS, 'glob');
    }

    protected function configureRoutes(RouteCollectionBuilder $routes): void
    {
        $confDir = $this->getProjectDir() . '/config';

        $routes->import($confDir . '/{routes}/' . $this->environment . '/**/*' . self::CONFIG_EXTS, '/', 'glob');
        $routes->import($confDir . '/{routes}/*' . self::CONFIG_EXTS, '/', 'glob');
        $routes->import($confDir . '/{routes}' . self::CONFIG_EXTS, '/', 'glob');
    }
}
