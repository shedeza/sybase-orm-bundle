<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\Command;

use SybaseORM\Metadata\EntityDiscovery;
use SybaseORM\Metadata\MetadataReaderInterface;
use SybaseORM\Proxy\ProxyGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Generates proxy classes for all mapped entities.
 */
#[AsCommand(
    name: 'sybase:proxy:generate',
    description: 'Generate proxy classes for lazy loading of all mapped entities',
)]
final class ProxyGenerateCommand extends Command
{
    public function __construct(
        private readonly ProxyGenerator $proxyGenerator,
        private readonly MetadataReaderInterface $metadataReader,
        /** @var string[] */
        private readonly array $entityDirectories,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Sybase ORM - Generate Proxies');

        $discovery = new EntityDiscovery($this->metadataReader);
        $entityClasses = $discovery->discoverEntityClasses($this->entityDirectories);

        if (empty($entityClasses)) {
            $io->warning('No entity classes found in configured directories.');

            return Command::SUCCESS;
        }

        $generated = 0;
        foreach ($entityClasses as $entityClass) {
            $proxyClass = $this->proxyGenerator->generateProxyClass($entityClass);
            $io->text(\sprintf('Generated proxy: %s', $proxyClass));
            $generated++;
        }

        $io->success(\sprintf('Generated %d proxy class(es).', $generated));

        return Command::SUCCESS;
    }
}
