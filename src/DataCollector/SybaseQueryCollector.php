<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\DataCollector;

use SybaseORM\Instrumentation\InstrumentationCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Throwable;

/**
 * Symfony Web Profiler DataCollector for SybaseORM.
 *
 * Reads instrumentation data from the ORM's native InstrumentationCollector
 * and presents it in the Symfony debug toolbar and profiler panel.
 */
final class SybaseQueryCollector extends DataCollector implements LateDataCollectorInterface
{
    private InstrumentationCollector $instrumentation;

    public function __construct(InstrumentationCollector $instrumentation)
    {
        $this->instrumentation = $instrumentation;
    }

    public function getName(): string
    {
        return 'sybase_orm';
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        // Data is collected by the InstrumentationCollector during the request
    }

    public function lateCollect(): void
    {
        $stats = $this->instrumentation->getStats();
        $queries = $this->instrumentation->getQueries();

        // Clone params for VarDumper (profiler_dump() requires Data objects)
        $clonedQueries = array_map(function (array $query): array {
            $query['params'] = $this->cloneVar($query['params']);

            return $query;
        }, $queries);

        $this->data = [
            'queries' => $clonedQueries,
            'query_count' => $stats['query_count'],
            'total_time' => $stats['total_query_time_ms'],
            'total_flush_time' => $stats['total_flush_time_ms'],
            'hydrations' => $stats['hydrations'],
            'collections' => $stats['collections'],
            'identity_hits' => $stats['identity_hits'],
            'identity_misses' => $stats['identity_misses'],
            'cache_hits' => $stats['cache_hits'],
            'cache_misses' => $stats['cache_misses'],
            'cache_writes' => $stats['cache_writes'],
            'lazy_loads' => $stats['lazy_loads'],
            'flushes' => $stats['flushes'],
            'transactions' => $stats['transactions'],
            'rollbacks' => $stats['rollbacks'],
            'connection_stats' => $this->buildConnectionStats($queries),
            'duplicate_queries' => $this->detectDuplicateQueries($queries),
            'slow_queries' => $this->instrumentation->getSlowQueries(100.0),
        ];
    }

    public function reset(): void
    {
        $this->data = [];
        $this->instrumentation->reset();
    }

    // ── Accessors for Twig template ─────────────────────────────────

    public function getQueryCount(): int
    {
        return $this->data['query_count'] ?? 0;
    }

    public function getTotalTime(): float
    {
        return $this->data['total_time'] ?? 0.0;
    }

    public function getTotalFlushTime(): float
    {
        return $this->data['total_flush_time'] ?? 0.0;
    }

    public function getQueries(): array
    {
        return $this->data['queries'] ?? [];
    }

    public function getHydrations(): int
    {
        return $this->data['hydrations'] ?? 0;
    }

    public function getCollections(): int
    {
        return $this->data['collections'] ?? 0;
    }

    public function getIdentityHits(): int
    {
        return $this->data['identity_hits'] ?? 0;
    }

    public function getIdentityMisses(): int
    {
        return $this->data['identity_misses'] ?? 0;
    }

    public function getCacheHits(): int
    {
        return $this->data['cache_hits'] ?? 0;
    }

    public function getCacheMisses(): int
    {
        return $this->data['cache_misses'] ?? 0;
    }

    public function getCacheWrites(): int
    {
        return $this->data['cache_writes'] ?? 0;
    }

    public function getLazyLoads(): int
    {
        return $this->data['lazy_loads'] ?? 0;
    }

    public function getFlushes(): int
    {
        return $this->data['flushes'] ?? 0;
    }

    public function getTransactions(): int
    {
        return $this->data['transactions'] ?? 0;
    }

    public function getRollbacks(): int
    {
        return $this->data['rollbacks'] ?? 0;
    }

    public function getConnectionStats(): array
    {
        return $this->data['connection_stats'] ?? [];
    }

    public function getDuplicateQueries(): array
    {
        return $this->data['duplicate_queries'] ?? [];
    }

    public function getSlowQueries(): array
    {
        return $this->data['slow_queries'] ?? [];
    }

    public function hasWarnings(): bool
    {
        return !empty($this->data['duplicate_queries'])
            || !empty($this->data['slow_queries'])
            || ($this->data['lazy_loads'] ?? 0) > 5
            || ($this->data['rollbacks'] ?? 0) > 0;
    }

    // ── Internal analysis ───────────────────────────────────────────

    private function buildConnectionStats(array $queries): array
    {
        $stats = [];
        foreach ($queries as $query) {
            $conn = $query['connection'];
            if (!isset($stats[$conn])) {
                $stats[$conn] = ['queries' => 0, 'time' => 0.0];
            }
            $stats[$conn]['queries']++;
            $stats[$conn]['time'] += $query['time_ms'] ?? 0.0;
        }

        return $stats;
    }

    private function detectDuplicateQueries(array $queries): array
    {
        $counts = [];
        foreach ($queries as $query) {
            $key = $query['sql'];
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return array_filter($counts, static fn(int $count): bool => $count > 1);
    }
}
