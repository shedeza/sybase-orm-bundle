<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use SybaseORM\Bundle\Command\ProxyGenerateCommand;
use SybaseORM\Metadata\MetadataReaderInterface;
use SybaseORM\Proxy\ProxyGenerator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ProxyGenerateCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/sybase_orm_proxy_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testExecuteWithNoEntities(): void
    {
        $proxyGenerator = new ProxyGenerator($this->tempDir);
        $metadataReader = $this->createMock(MetadataReaderInterface::class);

        $command = new ProxyGenerateCommand(
            $proxyGenerator,
            $metadataReader,
            [$this->tempDir],
        );

        $application = new Application();
        $application->add($command);

        $command = $application->find('sybase:proxy:generate');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('No entity classes found in configured directories.', $commandTester->getDisplay());
    }
}
