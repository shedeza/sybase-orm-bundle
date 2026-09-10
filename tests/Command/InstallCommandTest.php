<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use SybaseORM\Bundle\Command\InstallCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class InstallCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/sybase_orm_test_' . uniqid();
        mkdir($this->projectDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    public function testExecuteCreatesRequiredFiles(): void
    {
        $command = new InstallCommand($this->projectDir);
        $application = new Application();
        $application->add($command);

        $command = $application->find('sybase:install');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);
        
        $commandTester->assertCommandIsSuccessful();

        $this->assertFileExists($this->projectDir . '/config/packages/sybase_orm.yaml');
        $this->assertFileExists($this->projectDir . '/.env');
        $this->assertDirectoryExists($this->projectDir . '/sybase_ase/migrations');
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
