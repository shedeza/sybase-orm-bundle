<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\CacheWarmer;

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
    ) {
    }

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
            // Paquete no encontrado en composer.lock, nada que hacer
            return [];
        }

        $versionFilePath = $this->proxyDirectory . '/' . self::VERSION_FILE;
        $previousVersion = $this->getPreviousVersion($versionFilePath);

        if ($previousVersion === $currentVersion && $this->proxiesExist()) {
            // Misma versión y proxies existen — no regenerar
            return [];
        }

        // Versión nueva o proxies no existen — regenerar
        $this->clearProxies();
        $this->generateAllProxies();
        $this->saveVersion($versionFilePath, $currentVersion);

        return [];
    }

    /**
     * Lee la versión instalada de shedeza/sybase-orm desde composer.lock.
     */
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
        if (!is_array($lock)) {
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

    /**
     * Obtiene la versión previamente guardada.
     */
    private function getPreviousVersion(string $versionFilePath): ?string
    {
        if (!file_exists($versionFilePath)) {
            return null;
        }

        $version = file_get_contents($versionFilePath);

        return $version !== false ? trim($version) : null;
    }

    /**
     * Guarda la versión actual en el archivo de control.
     */
    private function saveVersion(string $versionFilePath, string $version): void
    {
        $dir = dirname($versionFilePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($versionFilePath, $version);
    }

    /**
     * Verifica si ya existen archivos de proxy generados.
     */
    private function proxiesExist(): bool
    {
        if (!is_dir($this->proxyDirectory)) {
            return false;
        }

        $files = glob($this->proxyDirectory . '/*Proxy.php');

        return !empty($files);
    }

    /**
     * Elimina todos los proxies generados previamente.
     */
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

    /**
     * Genera proxies para todas las entidades encontradas.
     */
    private function generateAllProxies(): void
    {
        $entityClasses = $this->discoverEntityClasses();

        foreach ($entityClasses as $entityClass) {
            $this->proxyGenerator->generateProxyClass($entityClass);
        }
    }

    /**
     * Descubre las clases de entidad desde los directorios configurados.
     *
     * @return string[]
     */
    private function discoverEntityClasses(): array
    {
        $classes = [];

        foreach ($this->entityDirectories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $className = $this->extractClassName($file->getPathname());
                if ($className !== null && $this->metadataReader->isEntity($className)) {
                    $classes[] = $className;
                }
            }
        }

        return $classes;
    }

    /**
     * Extrae el nombre de clase completo (FQCN) de un archivo PHP.
     */
    private function extractClassName(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return null;
        }

        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)/', $contents, $matches)) {
            $class = $matches[1];
        }

        if ($class === null) {
            return null;
        }

        return $namespace !== null ? $namespace . '\\' . $class : $class;
    }
}
