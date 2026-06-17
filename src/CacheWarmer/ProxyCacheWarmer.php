<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\CacheWarmer;

use SybaseORM\Metadata\EntityDiscovery;
use SybaseORM\Metadata\MetadataReaderInterface;
use SybaseORM\Proxy\ProxyGenerator;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Regenera automáticamente los proxies cuando se detecta que el paquete
 * shedeza/sybase-orm fue actualizado (cambió su versión en composer.lock).
 *
 * Se ejecuta durante cache:clear y al compilar el contenedor (después de composer update).
 */
final class ProxyCacheWarmer implements CacheWarmerInterface
{
    private const VERSION_FILE = 'sybase_orm_package.version';
    private const PACKAGE_NAME = 'shedeza/sybase-orm';

    public function __construct(
        private readonly ProxyGenerator $proxyGenerator,
        private readonly MetadataReaderInterface $metadataReader,
        /** @var string[] */
        private readonly array $entityDirectories,
        private readonly string $proxyDirectory,
        private readonly string $projectDir,
    ) {}

    public function isOptional(): bool
    {
        return true;
    }

    /**
     * @return string[]
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $currentVersion = $this->getInstalledPackageVersion();

        if ($currentVersion === null) {
            return [];
        }

        $versionFilePath = $this->proxyDirectory . '/' . self::VERSION_FILE;
        $previousVersion = $this->getPreviousVersion($versionFilePath);

        if ($previousVersion === $currentVersion && $this->proxiesExist()) {
            return [];
        }

        $this->clearProxies();
        $this->generateAllProxies();
        $this->saveVersion($versionFilePath, $currentVersion);

        return [];
    }

    private function getInstalledPackageVersion(): ?string
    {
        $composerLockPath = $this->projectDir . '/composer.lock';

        if (!file_exists($composerLockPath)) {
            return null;
        }

        $content = file_get_contents($composerLockPath);
        if ($content === false) {
            return null;
        }

        $lock = json_decode($content, true);
        if (!\is_array($lock)) {
            return null;
        }

        $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);

        foreach ($packages as $package) {
            if (($package['name'] ?? '') === self::PACKAGE_NAME) {
                return $package['version'] ?? null;
            }
        }

        return null;
    }

    private function getPreviousVersion(string $versionFilePath): ?string
    {
        if (!file_exists($versionFilePath)) {
            return null;
        }

        $version = file_get_contents($versionFilePath);

        return $version !== false ? trim($version) : null;
    }

    private function saveVersion(string $versionFilePath, string $version): void
    {
        $dir = \dirname($versionFilePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }

        file_put_contents($versionFilePath, $version);
    }

    private function proxiesExist(): bool
    {
        if (!is_dir($this->proxyDirectory)) {
            return false;
        }

        $files = glob($this->proxyDirectory . '/*Proxy.php');

        return !empty($files);
    }

    private function clearProxies(): void
    {
        if (!is_dir($this->proxyDirectory)) {
            return;
        }

        $files = glob($this->proxyDirectory . '/*Proxy.php');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            @unlink($file);
        }
    }

    private function generateAllProxies(): void
    {
        $discovery = new EntityDiscovery($this->metadataReader);
        $entityClasses = $discovery->discoverEntityClasses($this->entityDirectories);

        foreach ($entityClasses as $entityClass) {
            $this->proxyGenerator->generateProxyClass($entityClass);
        }
    }
}
