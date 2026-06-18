<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use SybaseORM\Bundle\DataCollector\SybaseQueryCollector;
use SybaseORM\Instrumentation\InstrumentationCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SybaseQueryCollectorTest extends TestCase
{
    private InstrumentationCollector $instrumentation;
    private SybaseQueryCollector $collector;

    protected function setUp(): void
    {
        $this->instrumentation = new InstrumentationCollector();
        $this->collector = new SybaseQueryCollector($this->instrumentation);
    }

    public function testGetName(): void
    {
        $this->assertSame('sybase_orm', $this->collector->getName());
    }

    public function testInitialState(): void
    {
        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $this->assertSame(0, $this->collector->getQueryCount());
        $this->assertSame(0.0, $this->collector->getTotalTime());
        $this->assertSame([], $this->collector->getQueries());
        $this->assertSame(0, $this->collector->getHydrations());
        $this->assertSame(0, $this->collector->getIdentityHits());
        $this->assertSame(0, $this->collector->getLazyLoads());
        $this->assertFalse($this->collector->hasWarnings());
    }

    public function testQueryTracking(): void
    {
        $this->instrumentation->onQueryStart('SELECT * FROM users', ['param1'], 'default');
        $this->instrumentation->onQueryEnd('SELECT * FROM users', ['param1'], 'default', 5.5);

        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $this->assertSame(1, $this->collector->getQueryCount());
        $this->assertSame(5.5, $this->collector->getTotalTime());

        $queries = $this->collector->getQueries();
        $this->assertCount(1, $queries);
        $this->assertSame('SELECT * FROM users', $queries[0]['sql']);
        $this->assertSame(5.5, $queries[0]['time_ms']);
        $this->assertSame('default', $queries[0]['connection']);
    }

    public function testMultipleQueries(): void
    {
        $this->instrumentation->onQueryStart('SELECT 1', [], 'primary');
        $this->instrumentation->onQueryEnd('SELECT 1', [], 'primary', 1.0);
        $this->instrumentation->onQueryStart('SELECT 2', [], 'primary');
        $this->instrumentation->onQueryEnd('SELECT 2', [], 'primary', 2.0);
        $this->instrumentation->onQueryStart('SELECT 3', [], 'reporting');
        $this->instrumentation->onQueryEnd('SELECT 3', [], 'reporting', 3.0);

        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $this->assertSame(3, $this->collector->getQueryCount());
        $this->assertSame(6.0, $this->collector->getTotalTime());

        $stats = $this->collector->getConnectionStats();
        $this->assertSame(2, $stats['primary']['queries']);
        $this->assertSame(3.0, $stats['primary']['time']);
        $this->assertSame(1, $stats['reporting']['queries']);
        $this->assertSame(3.0, $stats['reporting']['time']);
    }

    public function testDuplicateQueryDetection(): void
    {
        $this->instrumentation->onQueryStart('SELECT * FROM users WHERE id = ?', [1], 'default');
        $this->instrumentation->onQueryEnd('SELECT * FROM users WHERE id = ?', [1], 'default', 1.0);
        $this->instrumentation->onQueryStart('SELECT * FROM users WHERE id = ?', [2], 'default');
        $this->instrumentation->onQueryEnd('SELECT * FROM users WHERE id = ?', [2], 'default', 1.0);
        $this->instrumentation->onQueryStart('SELECT * FROM products', [], 'default');
        $this->instrumentation->onQueryEnd('SELECT * FROM products', [], 'default', 1.0);

        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $duplicates = $this->collector->getDuplicateQueries();
        $this->assertArrayHasKey('SELECT * FROM users WHERE id = ?', $duplicates);
        $this->assertSame(2, $duplicates['SELECT * FROM users WHERE id = ?']);
        $this->assertArrayNotHasKey('SELECT * FROM products', $duplicates);
        $this->assertTrue($this->collector->hasWarnings());
    }

    public function testSlowQueryDetection(): void
    {
        $this->instrumentation->onQueryStart('SELECT * FROM fast', [], 'default');
        $this->instrumentation->onQueryEnd('SELECT * FROM fast', [], 'default', 10.0);
        $this->instrumentation->onQueryStart('SELECT * FROM slow', [], 'default');
        $this->instrumentation->onQueryEnd('SELECT * FROM slow', [], 'default', 150.0);

        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $slow = $this->collector->getSlowQueries();
        $this->assertCount(1, $slow);
        $this->assertSame('SELECT * FROM slow', $slow[0]['sql']);
        $this->assertTrue($this->collector->hasWarnings());
    }

    public function testHydrationTracking(): void
    {
        $this->instrumentation->onEntityHydrated('App\\Entity\\User', 1);
        $this->instrumentation->onEntityHydrated('App\\Entity\\User', 2);
        $this->instrumentation->onEntityHydrated('App\\Entity\\Product', 1);

        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $this->assertSame(3, $this->collector->getHydrations());
    }

    public function testIdentityMapTracking(): void
    {
        $this->instrumentation->onIdentityMapHit('App\\Entity\\User', 1);
        $this->instrumentation->onIdentityMapHit('App\\Entity\\User', 2);
        $this->instrumentation->onIdentityMapMiss('App\\Entity\\User', 3);

        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $this->assertSame(2, $this->collector->getIdentityHits());
        $this->assertSame(1, $this->collector->getIdentityMisses());
    }

    public function testLazyLoadTracking(): void
    {
        $this->instrumentation->onLazyLoad('App\\Entity\\Order', 1);
        $this->instrumentation->onLazyLoad('App\\Entity\\Order', 2);

        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $this->assertSame(2, $this->collector->getLazyLoads());
    }

    public function testExcessiveLazyLoadWarning(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->instrumentation->onLazyLoad('App\\Entity\\Order', $i);
        }

        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $this->assertTrue($this->collector->hasWarnings());
    }

    public function testCacheTracking(): void
    {
        $this->instrumentation->onCacheHit('entity:User:1');
        $this->instrumentation->onCacheHit('entity:User:2');
        $this->instrumentation->onCacheMiss('entity:User:3');
        $this->instrumentation->onCacheWrite('entity:User:3');

        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $this->assertSame(2, $this->collector->getCacheHits());
        $this->assertSame(1, $this->collector->getCacheMisses());
        $this->assertSame(1, $this->collector->getCacheWrites());
    }

    public function testTransactionTracking(): void
    {
        $this->instrumentation->onTransactionBegin();
        $this->instrumentation->onTransactionCommit(10.0);
        $this->instrumentation->onTransactionBegin();
        $this->instrumentation->onTransactionRollback('Constraint violation');

        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $this->assertSame(2, $this->collector->getTransactions());
        $this->assertSame(1, $this->collector->getRollbacks());
        $this->assertTrue($this->collector->hasWarnings()); // rollback triggers warning
    }

    public function testFlushTracking(): void
    {
        $this->instrumentation->onFlushStart(3, 1, 0);
        $this->instrumentation->onFlushEnd(25.5);

        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $this->assertSame(1, $this->collector->getFlushes());
        $this->assertSame(25.5, $this->collector->getTotalFlushTime());
    }

    public function testReset(): void
    {
        $this->instrumentation->onQueryStart('SELECT 1', [], 'default');
        $this->instrumentation->onQueryEnd('SELECT 1', [], 'default', 1.0);
        $this->instrumentation->onEntityHydrated('App\\Entity\\User', 1);

        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();
        $this->collector->reset();

        $this->assertSame(0, $this->collector->getQueryCount());
        $this->assertSame(0.0, $this->collector->getTotalTime());
        $this->assertSame([], $this->collector->getQueries());
    }

    public function testNoWarningsWhenClean(): void
    {
        $this->instrumentation->onQueryStart('SELECT 1', [], 'default');
        $this->instrumentation->onQueryEnd('SELECT 1', [], 'default', 10.0);
        $this->instrumentation->onQueryStart('SELECT 2', [], 'default');
        $this->instrumentation->onQueryEnd('SELECT 2', [], 'default', 20.0);

        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $this->assertFalse($this->collector->hasWarnings());
    }
}
