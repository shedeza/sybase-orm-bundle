<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\Command;

use SybaseORM\Metadata\EntityDiscovery;
use SybaseORM\Metadata\MetadataReaderInterface;
use SybaseORM\Migration\MigrationManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Generates a new migration file by comparing entity metadata with the current schema.
 */
#[AsCommand(
    name: 'sybase:migrations:generate',
    description: 'Generate a new migration by comparing entity metadata with the database schema',
)]
final class MigrationsGenerateCommand extends Command
{
    public function __construct(
        private readonly MigrationManager $migrationManager,
        private readonly MetadataReaderInterface $metadataReader,
        /** @var string[] */
        private readonly array $entityDirectories,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Sybase ORM - Generate Migration');

        $discovery = new EntityDiscovery($this->metadataReader);
        $entityClasses = $discovery->discoverEntityClasses($this->entityDirectories);

        if (empty($entityClasses)) {
            $io->warning('No entity classes found in configured directories.');

            return Command::SUCCESS;
        }

        $io->text(\sprintf('Found %d entity class(es).', \count($entityClasses)));

        $filePath = $this->migrationManager->generateMigration($entityClasses);

        if ($filePath === null) {
            $io->success('No schema changes detected. No migration generated.');

            return Command::SUCCESS;
        }

        $io->success(\sprintf('Migration generated: %s', $filePath));

        return Command::SUCCESS;
    }
}
