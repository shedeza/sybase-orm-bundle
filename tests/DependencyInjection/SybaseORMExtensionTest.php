<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use SybaseORM\Bundle\CacheWarmer\ProxyCacheWarmer;
use SybaseORM\Bundle\Command\InstallCommand;
use SybaseORM\Bundle\Command\ProxyGenerateCommand;
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

        // Bundle-specific commands
        $this->assertTrue($this->container->hasDefinition(InstallCommand::class));
        $this->assertTrue($this->container->hasDefinition(ProxyGenerateCommand::class));

        // ORM native commands (via adapter)
        $this->assertTrue($this->container->hasDefinition('sybase_orm.cmd.migrate'));
        $this->assertTrue($this->container->hasDefinition('sybase_orm.cmd.migrate_status'));
        $this->assertTrue($this->container->hasDefinition('sybase_orm.cmd.migrate_generate'));
        $this->assertTrue($this->container->hasDefinition('sybase_orm.cmd.migrate_rollback'));
        $this->assertTrue($this->container->hasDefinition('sybase_orm.cmd.migrate_reset'));
        $this->assertTrue($this->container->hasDefinition('sybase_orm.cmd.migrate_fresh'));
        $this->assertTrue($this->container->hasDefinition('sybase_orm.cmd.migrate_preview'));
        $this->assertTrue($this->container->hasDefinition('sybase_orm.cmd.schema_validate'));
        $this->assertTrue($this->container->hasDefinition('sybase_orm.cmd.cache_clear'));
        $this->assertTrue($this->container->hasDefinition('sybase_orm.cmd.make_entity'));
        $this->assertTrue($this->container->hasDefinition('sybase_orm.cmd.orm_info'));
    }

    public function testCommandsHaveConsoleCommandTag(): void
    {
        $this->loadWithDefaultConnection();

        $commands = [
            InstallCommand::class,
            ProxyGenerateCommand::class,
            'sybase_orm.cmd.migrate',
            'sybase_orm.cmd.migrate_status',
            'sybase_orm.cmd.cache_clear',
            'sybase_orm.cmd.schema_validate',
            'sybase_orm.cmd.make_entity',
            'sybase_orm.cmd.orm_info',
        ];

        foreach ($commands as $serviceId) {
            $definition = $this->container->getDefinition($serviceId);
            $this->assertTrue(
                $definition->hasTag('console.command'),
                \sprintf('%s should have console.command tag', $serviceId),
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
