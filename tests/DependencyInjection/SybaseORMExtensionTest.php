<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use SybaseORM\Bundle\CacheWarmer\ProxyCacheWarmer;
use SybaseORM\Bundle\Command\CacheClearCommand;
use SybaseORM\Bundle\Command\InstallCommand;
use SybaseORM\Bundle\Command\MigrationsGenerateCommand;
use SybaseORM\Bundle\Command\MigrationsMigrateCommand;
use SybaseORM\Bundle\Command\ProxyGenerateCommand;
use SybaseORM\Bundle\Command\SchemaValidateCommand;
use SybaseORM\Bundle\DependencyInjection\SybaseORMExtension;
use SybaseORM\Connection\ConnectionManagerInterface;
use SybaseORM\Dialect\DialectInterface;
use SybaseORM\Hook\HookDispatcher;
use SybaseORM\Hydrator\HydratorInterface;
use SybaseORM\Instrumentation\OrmInstrumentationInterface;
use SybaseORM\Metadata\MetadataReaderInterface;
use SybaseORM\ORM\EntityManagerInterface;
use SybaseORM\ORM\EntityManagerRegistry;
use SybaseORM\ORM\IdentityMapInterface;
use SybaseORM\ORM\UnitOfWorkInterface;
use SybaseORM\Proxy\ProxyGenerator;
use SybaseORM\Type\TypeCasterInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SybaseORMExtensionTest extends TestCase
{
    private ContainerBuilder $container;
    private SybaseORMExtension $extension;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.project_dir', '/tmp/test-project');
        $this->container->setParameter('kernel.cache_dir', '/tmp/test-project/var/cache');
        $this->container->setParameter('kernel.debug', true);
        $this->extension = new SybaseORMExtension();
    }

    public function testGetAlias(): void
    {
        $this->assertSame('sybase_orm', $this->extension->getAlias());
    }

    public function testNoConnectionRegistersOnlyInstallCommand(): void
    {
        $this->extension->load([], $this->container);

        // InstallCommand should be available even without configuration
        $this->assertTrue($this->container->hasDefinition(InstallCommand::class));

        // Core services should NOT be registered
        $this->assertFalse($this->container->has(EntityManagerInterface::class));
        $this->assertFalse($this->container->has(ConnectionManagerInterface::class));
    }

    public function testSingleConnectionRegistersAllServices(): void
    {
        $this->loadWithDefaultConnection();

        // Core interfaces registered
        $this->assertTrue($this->container->has(MetadataReaderInterface::class));
        $this->assertTrue($this->container->has(DialectInterface::class));
        $this->assertTrue($this->container->has(TypeCasterInterface::class));
        $this->assertTrue($this->container->has(ConnectionManagerInterface::class));
        $this->assertTrue($this->container->has(IdentityMapInterface::class));
        $this->assertTrue($this->container->has(HydratorInterface::class));
        $this->assertTrue($this->container->has(UnitOfWorkInterface::class));
        $this->assertTrue($this->container->has(EntityManagerInterface::class));
        $this->assertTrue($this->container->hasDefinition(EntityManagerRegistry::class));
    }

    public function testCommandsAreRegistered(): void
    {
        $this->loadWithDefaultConnection();

        $this->assertTrue($this->container->hasDefinition(InstallCommand::class));
        $this->assertTrue($this->container->hasDefinition(CacheClearCommand::class));
        $this->assertTrue($this->container->hasDefinition(SchemaValidateCommand::class));
        $this->assertTrue($this->container->hasDefinition(MigrationsGenerateCommand::class));
        $this->assertTrue($this->container->hasDefinition(MigrationsMigrateCommand::class));
        $this->assertTrue($this->container->hasDefinition(ProxyGenerateCommand::class));
    }

    public function testCommandsHaveConsoleCommandTag(): void
    {
        $this->loadWithDefaultConnection();

        $commands = [
            InstallCommand::class,
            CacheClearCommand::class,
            SchemaValidateCommand::class,
            MigrationsGenerateCommand::class,
            MigrationsMigrateCommand::class,
            ProxyGenerateCommand::class,
        ];

        foreach ($commands as $commandClass) {
            $definition = $this->container->getDefinition($commandClass);
            $this->assertTrue(
                $definition->hasTag('console.command'),
                \sprintf('%s should have console.command tag', $commandClass),
            );
        }
    }

    public function testCacheWarmerIsRegistered(): void
    {
        $this->loadWithDefaultConnection();

        $this->assertTrue($this->container->hasDefinition(ProxyCacheWarmer::class));
        $definition = $this->container->getDefinition(ProxyCacheWarmer::class);
        $this->assertTrue($definition->hasTag('kernel.cache_warmer'));
    }

    public function testProxyGeneratorIsRegistered(): void
    {
        $this->loadWithDefaultConnection();

        $this->assertTrue($this->container->hasDefinition(ProxyGenerator::class));
    }

    public function testHookDispatcherIsRegistered(): void
    {
        $this->loadWithDefaultConnection();

        $this->assertTrue($this->container->hasDefinition(HookDispatcher::class));
    }

    public function testParametersAreStored(): void
    {
        $this->loadWithDefaultConnection();

        $this->assertTrue($this->container->hasParameter('sybase_orm.entity_directories'));
        $this->assertTrue($this->container->hasParameter('sybase_orm.proxy_directory'));
        $this->assertTrue($this->container->hasParameter('sybase_orm.migrations_directory'));

        $this->assertSame(
            ['%kernel.project_dir%/src/Entity'],
            $this->container->getParameter('sybase_orm.entity_directories'),
        );
    }

    public function testMultipleConnectionsRegisterSeparateServices(): void
    {
        $this->extension->load([
            [
                'connections' => [
                    'primary' => ['url' => 'sybase://u:p@h:5000/db1'],
                    'reporting' => ['url' => 'sybase://u:p@h:5000/db2'],
                ],
            ],
        ], $this->container);

        // Both entity managers registered
        $this->assertTrue($this->container->hasDefinition('sybase_orm.entity_manager.primary'));
        $this->assertTrue($this->container->hasDefinition('sybase_orm.entity_manager.reporting'));

        // Primary is aliased to the interface
        $this->assertTrue($this->container->has(EntityManagerInterface::class));
    }

    public function testEntityManagerIsPublic(): void
    {
        $this->loadWithDefaultConnection();

        $definition = $this->container->getDefinition('sybase_orm.entity_manager.default');
        $this->assertTrue($definition->isPublic());
    }

    public function testEntityManagerRegistryIsPublic(): void
    {
        $this->loadWithDefaultConnection();

        $definition = $this->container->getDefinition(EntityManagerRegistry::class);
        $this->assertTrue($definition->isPublic());
    }

    public function testConnectionWithUrlUsesFactory(): void
    {
        $this->loadWithDefaultConnection();

        $definition = $this->container->getDefinition('sybase_orm.connection_manager.default');
        $this->assertNotNull($definition->getFactory());
    }

    public function testConnectionWithParamsUsesDirectInstantiation(): void
    {
        $this->extension->load([
            [
                'connection' => [
                    'host' => '10.0.0.1',
                    'port' => 5000,
                    'database' => 'testdb',
                    'username' => 'sa',
                    'password' => 'pass',
                ],
            ],
        ], $this->container);

        $definition = $this->container->getDefinition('sybase_orm.connection_manager.default');
        $this->assertNull($definition->getFactory());
    }

    public function testInstrumentationIsRegistered(): void
    {
        $this->loadWithDefaultConnection();

        $this->assertTrue($this->container->has(OrmInstrumentationInterface::class));
    }

    private function loadWithDefaultConnection(): void
    {
        $this->extension->load([
            [
                'connection' => ['url' => 'sybase://sa:pass@localhost:5000/testdb'],
            ],
        ], $this->container);
    }
}
