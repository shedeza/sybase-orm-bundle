<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\Tests\CacheWarmer;

use FilesystemIterator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SybaseORM\Bundle\CacheWarmer\ProxyCacheWarmer;
use SybaseORM\Metadata\MetadataReaderInterface;
use SybaseORM\Proxy\ProxyGenerator;

final class ProxyCacheWarmerTest extends TestCase
{
    private string $proxyDir;
    private string $projectDir;
    private ProxyGenerator $proxyGenerator;
    private MetadataReaderInterface&MockObject $metadataReader;

    protected function setUp(): void
    {
        $this->proxyDir = sys_get_temp_dir() . '/sybase_orm_test_proxies_' . uniqid();
        $this->projectDir = sys_get_temp_dir() . '/sybase_orm_test_project_' . uniqid();

        mkdir($this->proxyDir, 0o777, true);
        mkdir($this->projectDir, 0o777, true);

        $this->proxyGenerator = new ProxyGenerator($this->proxyDir);
        $this->metadataReader = $this->createMock(MetadataReaderInterface::class);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->proxyDir);
        $this->removeDir($this->projectDir);
    }

    public function testIsOptional(): void
    {
        $warmer = $this->createWarmer();

        $this->assertTrue($warmer->isOptional());
    }

    public function testSkipsWhenNoComposerLock(): void
    {
        $warmer = $this->createWarmer();

        // No composer.lock exists — version file should not be created
        $result = $warmer->warmUp('/tmp/cache');

        $this->assertSame([], $result);
        $this->assertFileDoesNotExist($this->proxyDir . '/sybase_orm_package.version');
    }

    public function testSkipsWhenPackageNotInLock(): void
    {
        $this->writeComposerLock([
            ['name' => 'other/package', 'version' => '1.0.0'],
        ]);

        $warmer = $this->createWarmer();
        $warmer->warmUp('/tmp/cache');

        // Version file should not be created since package was not found
        $this->assertFileDoesNotExist($this->proxyDir . '/sybase_orm_package.version');
    }

    public function testRegeneratesWhenVersionChanges(): void
    {
        $this->writeComposerLock([
            ['name' => 'shedeza/sybase-orm', 'version' => '3.1.0'],
        ]);

        // Write a previous version
        file_put_contents($this->proxyDir . '/sybase_orm_package.version', '3.0.0');

        $warmer = $this->createWarmer();

        // No entity directories exist, so no proxies generated, but version is saved
        $warmer->warmUp('/tmp/cache');

        // Version file should be updated
        $this->assertSame('3.1.0', file_get_contents($this->proxyDir . '/sybase_orm_package.version'));
    }

    public function testSkipsWhenSameVersionAndProxiesExist(): void
    {
        $this->writeComposerLock([
            ['name' => 'shedeza/sybase-orm', 'version' => '3.0.0'],
        ]);

        // Write same version
        file_put_contents($this->proxyDir . '/sybase_orm_package.version', '3.0.0');
        // Create a proxy file
        file_put_contents($this->proxyDir . '/App_Entity_UserProxy.php', '<?php // proxy');

        $warmer = $this->createWarmer();
        $warmer->warmUp('/tmp/cache');

        // Proxy file should still exist (not cleared)
        $this->assertFileExists($this->proxyDir . '/App_Entity_UserProxy.php');
        // Version unchanged
        $this->assertSame('3.0.0', file_get_contents($this->proxyDir . '/sybase_orm_package.version'));
    }

    public function testRegeneratesWhenProxiesMissing(): void
    {
        $this->writeComposerLock([
            ['name' => 'shedeza/sybase-orm', 'version' => '3.0.0'],
        ]);

        // Same version but no proxy files
        file_put_contents($this->proxyDir . '/sybase_orm_package.version', '3.0.0');

        $warmer = $this->createWarmer();

        // No entity dirs, so nothing to generate, but the logic path is exercised
        $warmer->warmUp('/tmp/cache');

        // Version file still present
        $this->assertSame('3.0.0', file_get_contents($this->proxyDir . '/sybase_orm_package.version'));
    }

    public function testClearsOldProxiesOnVersionChange(): void
    {
        $this->writeComposerLock([
            ['name' => 'shedeza/sybase-orm', 'version' => '3.2.0'],
        ]);

        // Old version + old proxy file
        file_put_contents($this->proxyDir . '/sybase_orm_package.version', '3.1.0');
        file_put_contents($this->proxyDir . '/OldEntityProxy.php', '<?php // old proxy');

        $warmer = $this->createWarmer();
        $warmer->warmUp('/tmp/cache');

        // Old proxy should be deleted
        $this->assertFileDoesNotExist($this->proxyDir . '/OldEntityProxy.php');
        // Version updated
        $this->assertSame('3.2.0', file_get_contents($this->proxyDir . '/sybase_orm_package.version'));
    }

    public function testFirstRunWithNoVersionFile(): void
    {
        $this->writeComposerLock([
            ['name' => 'shedeza/sybase-orm', 'version' => '3.0.0'],
        ]);

        $warmer = $this->createWarmer();
        $warmer->warmUp('/tmp/cache');

        // Version file should be created
        $this->assertFileExists($this->proxyDir . '/sybase_orm_package.version');
        $this->assertSame('3.0.0', file_get_contents($this->proxyDir . '/sybase_orm_package.version'));
    }

    private function createWarmer(): ProxyCacheWarmer
    {
        return new ProxyCacheWarmer(
            $this->proxyGenerator,
            $this->metadataReader,
            [],
            $this->proxyDir,
            $this->projectDir,
        );
    }

    /**
     * @param array<int, array{name: string, version: string}> $packages
     */
    private function writeComposerLock(array $packages): void
    {
        $lock = ['packages' => $packages, 'packages-dev' => []];
        file_put_contents($this->projectDir . '/composer.lock', json_encode($lock));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($dir);
    }
}
