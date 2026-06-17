<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\DataCollector;

use SybaseORM\Connection\ConnectionManagerInterface;

/**
 * Decorator for ConnectionManager that intercepts all queries and records
 * them in the SybaseQueryCollector for the Symfony Profiler.
 *
 * Active only in debug mode (kernel.debug = true).
 */
final class ProfilingConnectionManager implements ConnectionManagerInterface
{
    public function __construct(
        private readonly ConnectionManagerInterface $inner,
        private readonly SybaseQueryCollector $collector,
        private readonly string $connectionName = 'default',
        private readonly bool $collectBacktraces = false,
    ) {
    }

    public function getConnection(): \PDO
    {
        return $this->inner->getConnection();
    }

    public function executeQuery(string $sql, array $params = []): \PDOStatement
    {
        $start = microtime(true);

        try {
            $result = $this->inner->executeQuery($sql, $params);
        } finally {
            $time = (microtime(true) - $start) * 1000;
            $this->collector->addQuery(
                $sql,
                $params,
                $time,
                $this->connectionName,
                $this->collectBacktraces ? $this->getBacktrace() : null,
            );
        }

        return $result;
    }

    public function executeStatement(string $sql, array $params = []): int
    {
        $start = microtime(true);

        try {
            $result = $this->inner->executeStatement($sql, $params);
        } finally {
            $time = (microtime(true) - $start) * 1000;
            $this->collector->addQuery(
                $sql,
                $params,
                $time,
                $this->connectionName,
                $this->collectBacktraces ? $this->getBacktrace() : null,
            );
        }

        return $result;
    }

    public function beginTransaction(): void
    {
        $this->inner->beginTransaction();
    }

    public function commit(): void
    {
        $this->inner->commit();
    }

    public function rollback(): void
    {
        $this->inner->rollback();
    }

    public function setTransactionIsolation(string $level): void
    {
        $this->inner->setTransactionIsolation($level);
    }

    public function convertResultRow(array $row): array
    {
        return $this->inner->convertResultRow($row);
    }

    public function ping(): bool
    {
        return $this->inner->ping();
    }

    public function getServerVersion(): string
    {
        return $this->inner->getServerVersion();
    }

    public function isInTransaction(): bool
    {
        return $this->inner->isInTransaction();
    }

    /**
     * Genera un backtrace simplificado excluyendo frames internos del ORM.
     */
    private function getBacktrace(): string
    {
        $trace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 15);

        $lines = [];
        foreach ($trace as $frame) {
            $class = $frame['class'] ?? '';

            // Excluir frames internos del bundle y del ORM
            if (str_starts_with($class, 'SybaseORM\\Bundle\\DataCollector\\')) {
                continue;
            }

            $file = $frame['file'] ?? 'unknown';
            $line = $frame['line'] ?? 0;
            $function = ($class !== '' ? $class . '::' : '') . $frame['function'];

            $lines[] = sprintf('%s:%d → %s()', $file, $line, $function);

            if (count($lines) >= 8) {
                break;
            }
        }

        return implode("\n", $lines);
    }
}
