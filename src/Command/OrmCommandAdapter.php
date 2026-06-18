<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\Command;

use SybaseORM\Console\CommandInterface as OrmCommandInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Adapts ORM native CLI commands to Symfony Console commands.
 *
 * Wraps any SybaseORM\Console\CommandInterface as a Symfony command,
 * capturing output and presenting it through SymfonyStyle.
 */
final class OrmCommandAdapter extends Command
{
    private OrmCommandInterface $ormCommand;
    private string $symfonyName;

    public function __construct(OrmCommandInterface $ormCommand, string $symfonyName)
    {
        $this->ormCommand = $ormCommand;
        $this->symfonyName = $symfonyName;

        parent::__construct($this->symfonyName);
    }

    protected function configure(): void
    {
        $this
            ->setDescription($this->ormCommand->getDescription())
            ->addArgument('args', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Arguments passed to the ORM command');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $args = $input->getArgument('args') ?? [];

        // Capture ORM command output by temporarily redirecting
        ob_start();

        try {
            $exitCode = $this->ormCommand->execute($args);
        } finally {
            $captured = ob_get_clean();
        }

        // Display captured output through Symfony IO
        if ($captured !== false && $captured !== '') {
            $lines = explode("\n", rtrim($captured));
            foreach ($lines as $line) {
                $output->writeln($line);
            }
        }

        return $exitCode;
    }
}
