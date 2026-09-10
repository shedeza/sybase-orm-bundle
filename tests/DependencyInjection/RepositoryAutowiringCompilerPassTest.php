<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use SybaseORM\Bundle\DependencyInjection\RepositoryAutowiringCompilerPass;
use SybaseORM\ORM\EntityManagerRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class RepositoryAutowiringCompilerPassTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/sybase_orm_repo_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = array_diff(scandir($this->tempDir), ['.', '..']);
            foreach ($files as $file) {
                unlink($this->tempDir . '/' . $file);
            }
            rmdir($this->tempDir);
        }
    }

    public function testProcessWithoutEntityManagerRegistry(): void
    {
        $container = new ContainerBuilder();
        $compilerPass = new RepositoryAutowiringCompilerPass();
        
        $compilerPass->process($container);
        
        $this->assertFalse($container->has('some_repo'));
    }

    public function testProcessWithEmptyDirectories(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(EntityManagerRegistry::class, new Definition());
        $container->setParameter('sybase_orm.entity_directories', [$this->tempDir]);
        
        $compilerPass = new RepositoryAutowiringCompilerPass();
        $compilerPass->process($container);
        
        $this->assertTrue($container->has(EntityManagerRegistry::class));
    }
}
