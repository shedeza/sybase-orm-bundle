<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Throwable;

/**
 * Symfony Web Profiler DataCollector for SybaseORM.
 *
 * Collects executed queries, timing, connection info, hydration stats,
 * identity map usage, and UnitOfWork operations for display in the
 * Symfony debug toolbar and profiler panel.
 */
final class SybaseQueryCollector extends DataCollector implements LateDataCollectorInterface
{
    /** @var array<int, array{sql: string, params: array<mixed>, time: float, connection: string, backtrace: string|null}> */
    private array $queries = [];

    private float $totalTime = 0.0;

    private int $hydratedEntities = 0;

    private int $identityMapHits = 0;

    private int $identityMapSize = 0;

    /** @var array{persisted: int, updated: int, removed: int} */
    private array $unitOfWorkOps = ['persisted' => 0, 'updated' => 0, 'removed' => 0];

    /** @var array<string, array{queries: int, time: float}> */
    private array $connectionStats = [];

    public function getName(): string
    {
        return 'sybase_orm';
    }

    /**
     * Records a query execution.
     *
     * @param array<mixed> $params
     */
    public function addQuery(string $sql, array $params, float $timeMs, string $connection = 'default', ?string $backtrace = null): void
    {
        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
            'time' => $timeMs,
            'connection' => $connection,
            'backtrace' => $backtrace,
        ];
        $this->totalTime += $timeMs;

        if (!isset($this->connectionStats[$connection])) {
            $this->connectionStats[$connection] = ['queries' => 0, 'time' => 0.0];
        }
        $this->connectionStats[$connection]['queries']++;
        $this->connectionStats[$connection]['time'] += $timeMs;
    }

    /**
     * Records entity hydration.
     */
    public function addHydratedEntity(): void
    {
        $this->hydratedEntities++;
    }

    /**
     * Records an identity map cache hit.
     */
    public function addIdentityMapHit(): void
    {
        $this->identityMapHits++;
    }

    /**
     * Sets the current identity map size.
     */
    public function setIdentityMapSize(int $size): void
    {
        $this->identityMapSize = $size;
    }

    /**
     * Records a UnitOfWork operation.
     */
    public function addUnitOfWorkOperation(string $type): void
    {
        if (isset($this->unitOfWorkOps[$type])) {
            $this->unitOfWorkOps[$type]++;
        }
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        // Datos base se recopilan durante el request via addQuery(), etc.
    }

    public function lateCollect(): void
    {
        // Clone query params for the VarDumper (profiler_dump() requires Data objects)
        $queries = array_map(function (array $query): array {
            $query['params'] = $this->cloneVar($query['params']);

            return $query;
        }, $this->queries);

        $this->data = [
            'queries' => $queries,
            'query_count' => \count($this->queries),
            'total_time' => $this->totalTime,
            'hydrated_entities' => $this->hydratedEntities,
            'identity_map_hits' => $this->identityMapHits,
            'identity_map_size' => $this->identityMapSize,
            'unit_of_work' => $this->unitOfWorkOps,
            'connection_stats' => $this->connectionStats,
            'duplicate_queries' => $this->detectDuplicateQueries(),
            'slow_queries' => $this->detectSlowQueries(),
        ];
    }

    public function reset(): void
    {
        $this->data = [];
        $this->queries = [];
        $this->totalTime = 0.0;
        $this->hydratedEntities = 0;
        $this->identityMapHits = 0;
        $this->identityMapSize = 0;
        $this->unitOfWorkOps = ['persisted' => 0, 'updated' => 0, 'removed' => 0];
        $this->connectionStats = [];
    }

    // --- Accessors for the Twig template ---

    public function getQueryCount(): int
    {
        return $this->data['query_count'] ?? 0;
    }

    public function getTotalTime(): float
    {
        return $this->data['total_time'] ?? 0.0;
    }

    /**
     * @return array<int, array{sql: string, params: array<mixed>, time: float, connection: string, backtrace: string|null}>
     */
    public function getQueries(): array
    {
        return $this->data['queries'] ?? [];
    }

    public function getHydratedEntities(): int
    {
        return $this->data['hydrated_entities'] ?? 0;
    }

    public function getIdentityMapHits(): int
    {
        return $this->data['identity_map_hits'] ?? 0;
    }

    public function getIdentityMapSize(): int
    {
        return $this->data['identity_map_size'] ?? 0;
    }

    /**
     * @return array{persisted: int, updated: int, removed: int}
     */
    public function getUnitOfWork(): array
    {
        return $this->data['unit_of_work'] ?? ['persisted' => 0, 'updated' => 0, 'removed' => 0];
    }

    /**
     * @return array<string, array{queries: int, time: float}>
     */
    public function getConnectionStats(): array
    {
        return $this->data['connection_stats'] ?? [];
    }

    /**
     * @return array<string, int>
     */
    public function getDuplicateQueries(): array
    {
        return $this->data['duplicate_queries'] ?? [];
    }

    /**
     * @return array<int, array{sql: string, params: array<mixed>, time: float, connection: string, backtrace: string|null}>
     */
    public function getSlowQueries(): array
    {
        return $this->data['slow_queries'] ?? [];
    }

    public function hasWarnings(): bool
    {
        return !empty($this->data['duplicate_queries']) || !empty($this->data['slow_queries']);
    }

    // --- Internal analysis ---

    /**
     * Detecta queries duplicadas (misma SQL ejecutada múltiples veces).
     *
     * @return array<string, int>
     */
    private function detectDuplicateQueries(): array
    {
        $counts = [];
        foreach ($this->queries as $query) {
            $key = $query['sql'];
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return array_filter($counts, static fn(int $count): bool => $count > 1);
    }

    /**
     * Detecta queries lentas (>100ms).
     *
     * @return array<int, array{sql: string, params: array<mixed>, time: float, connection: string, backtrace: string|null}>
     */
    private function detectSlowQueries(): array
    {
        return array_filter(
            $this->queries,
            static fn(array $query): bool => $query['time'] > 100.0,
        );
    }
}
