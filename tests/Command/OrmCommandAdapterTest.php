<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use SybaseORM\Bundle\Command\OrmCommandAdapter;
use SybaseORM\Console\CommandInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class OrmCommandAdapterTest extends TestCase
{
    public function testExecuteDelegatesToOrmCommand(): void
    {
        $ormCommand = $this->createMock(CommandInterface::class);
        $ormCommand->method('getName')->willReturn('fake:command');
        $ormCommand->method('getDescription')->willReturn('Fake description');
        
        $ormCommand->expects($this->once())
            ->method('execute')
            ->with(['--force', 'some_arg'])
            ->willReturnCallback(function () {
                echo "Some output from ORM command\n";
                return 0;
            });

        $adapter = new OrmCommandAdapter($ormCommand, 'sybase:fake:command');

        $application = new Application();
        $application->add($adapter);

        $command = $application->find('sybase:fake:command');
        $commandTester = new CommandTester($command);
        
        $commandTester->execute([
            'args' => ['--force', 'some_arg'],
        ]);

        $commandTester->assertCommandIsSuccessful();
        
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Some output from ORM command', $output);
    }
}
