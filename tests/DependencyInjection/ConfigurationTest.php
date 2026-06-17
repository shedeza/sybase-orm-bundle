<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use SybaseORM\Bundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    private Processor $processor;
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->processor = new Processor();
        $this->configuration = new Configuration();
    }

    public function testDefaultConfiguration(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, []);

        $this->assertArrayNotHasKey('connection', $config);
        $this->assertSame([], $config['connections']);
        $this->assertSame(['%kernel.project_dir%/src/Entity'], $config['entity_directories']);
        $this->assertSame('%kernel.cache_dir%/sybase_orm/proxies', $config['proxy_directory']);
        $this->assertSame('%kernel.project_dir%/sybase_ase/migrations', $config['migrations_directory']);
        $this->assertFalse($config['cache']['enabled']);
        $this->assertNull($config['cache']['adapter']);
        $this->assertNull($config['cache']['dsn']);
        $this->assertSame(3600, $config['cache']['default_ttl']);
    }

    public function testConnectionWithUrl(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            ['connection' => ['url' => 'sybase://user:pass@host:5000/db']],
        ]);

        $this->assertSame('sybase://user:pass@host:5000/db', $config['connection']['url']);
    }

    public function testConnectionWithIndividualParams(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            ['connection' => [
                'host' => '192.168.1.10',
                'port' => 4100,
                'database' => 'mydb',
                'username' => 'admin',
                'password' => 'secret',
                'charset' => 'iso_1',
                'persistent' => true,
                'charset_conversion' => true,
                'read_only' => true,
            ]],
        ]);

        $this->assertSame('192.168.1.10', $config['connection']['host']);
        $this->assertSame(4100, $config['connection']['port']);
        $this->assertSame('mydb', $config['connection']['database']);
        $this->assertSame('admin', $config['connection']['username']);
        $this->assertSame('secret', $config['connection']['password']);
        $this->assertSame('iso_1', $config['connection']['charset']);
        $this->assertTrue($config['connection']['persistent']);
        $this->assertTrue($config['connection']['charset_conversion']);
        $this->assertTrue($config['connection']['read_only']);
    }

    public function testConnectionWithoutUrlOrRequiredParamsThrows(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/url.*database.*username/i');

        $this->processor->processConfiguration($this->configuration, [
            ['connection' => ['host' => 'localhost']],
        ]);
    }

    public function testMultipleConnections(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            ['connections' => [
                'primary' => ['url' => 'sybase://user:pass@host1:5000/db1'],
                'reporting' => ['url' => 'sybase://user:pass@host2:5000/db2'],
            ]],
        ]);

        $this->assertArrayHasKey('primary', $config['connections']);
        $this->assertArrayHasKey('reporting', $config['connections']);
        $this->assertSame('sybase://user:pass@host1:5000/db1', $config['connections']['primary']['url']);
        $this->assertSame('sybase://user:pass@host2:5000/db2', $config['connections']['reporting']['url']);
    }

    public function testEntityDirectoriesOverride(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            ['entity_directories' => ['/app/src/Domain', '/app/src/Infrastructure']],
        ]);

        $this->assertSame(['/app/src/Domain', '/app/src/Infrastructure'], $config['entity_directories']);
    }

    public function testCacheConfiguration(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            ['cache' => [
                'enabled' => true,
                'adapter' => 'redis',
                'dsn' => 'redis://localhost:6379',
                'default_ttl' => 7200,
            ]],
        ]);

        $this->assertTrue($config['cache']['enabled']);
        $this->assertSame('redis', $config['cache']['adapter']);
        $this->assertSame('redis://localhost:6379', $config['cache']['dsn']);
        $this->assertSame(7200, $config['cache']['default_ttl']);
    }

    public function testProxyDirectoryOverride(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            ['proxy_directory' => '/tmp/proxies'],
        ]);

        $this->assertSame('/tmp/proxies', $config['proxy_directory']);
    }

    public function testMigrationsDirectoryOverride(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            ['migrations_directory' => '/app/migrations/sybase'],
        ]);

        $this->assertSame('/app/migrations/sybase', $config['migrations_directory']);
    }

    public function testConnectionDefaultValues(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            ['connection' => ['url' => 'sybase://u:p@h:5000/db']],
        ]);

        $this->assertSame('localhost', $config['connection']['host']);
        $this->assertSame(5000, $config['connection']['port']);
        $this->assertSame('UTF-8', $config['connection']['charset']);
        $this->assertFalse($config['connection']['persistent']);
        $this->assertFalse($config['connection']['charset_conversion']);
        $this->assertFalse($config['connection']['read_only']);
    }
}
