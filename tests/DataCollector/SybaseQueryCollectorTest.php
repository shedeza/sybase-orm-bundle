<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use SybaseORM\Bundle\DataCollector\SybaseQueryCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SybaseQueryCollectorTest extends TestCase
{
    private SybaseQueryCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new SybaseQueryCollector();
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
        $this->assertSame(0, $this->collector->getHydratedEntities());
        $this->assertSame(0, $this->collector->getIdentityMapHits());
        $this->assertSame(0, $this->collector->getIdentityMapSize());
        $this->assertFalse($this->collector->hasWarnings());
    }

    public function testAddQuery(): void
    {
        $this->collector->addQuery('SELECT * FROM users', ['param1'], 5.5, 'default');
        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $this->assertSame(1, $this->collector->getQueryCount());
        $this->assertSame(5.5, $this->collector->getTotalTime());

        $queries = $this->collector->getQueries();
        $this->assertCount(1, $queries);
        $this->assertSame('SELECT * FROM users', $queries[0]['sql']);
        $this->assertSame(['param1'], $queries[0]['params']);
        $this->assertSame(5.5, $queries[0]['time']);
        $this->assertSame('default', $queries[0]['connection']);
    }

    public function testMultipleQueries(): void
    {
        $this->collector->addQuery('SELECT 1', [], 1.0, 'primary');
        $this->collector->addQuery('SELECT 2', [], 2.0, 'primary');
        $this->collector->addQuery('SELECT 3', [], 3.0, 'reporting');
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
        $this->collector->addQuery('SELECT * FROM users WHERE id = ?', [1], 1.0);
        $this->collector->addQuery('SELECT * FROM users WHERE id = ?', [2], 1.0);
        $this->collector->addQuery('SELECT * FROM products', [], 1.0);
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
        $this->collector->addQuery('SELECT * FROM fast', [], 10.0);
        $this->collector->addQuery('SELECT * FROM slow', [], 150.0);
        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $slow = $this->collector->getSlowQueries();
        $this->assertCount(1, $slow);
        $this->assertSame('SELECT * FROM slow', array_values($slow)[0]['sql']);
        $this->assertTrue($this->collector->hasWarnings());
    }

    public function testHydratedEntities(): void
    {
        $this->collector->addHydratedEntity();
        $this->collector->addHydratedEntity();
        $this->collector->addHydratedEntity();
        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $this->assertSame(3, $this->collector->getHydratedEntities());
    }

    public function testIdentityMapTracking(): void
    {
        $this->collector->addIdentityMapHit();
        $this->collector->addIdentityMapHit();
        $this->collector->setIdentityMapSize(42);
        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $this->assertSame(2, $this->collector->getIdentityMapHits());
        $this->assertSame(42, $this->collector->getIdentityMapSize());
    }

    public function testUnitOfWorkOperations(): void
    {
        $this->collector->addUnitOfWorkOperation('persisted');
        $this->collector->addUnitOfWorkOperation('persisted');
        $this->collector->addUnitOfWorkOperation('updated');
        $this->collector->addUnitOfWorkOperation('removed');
        $this->collector->addUnitOfWorkOperation('invalid'); // should be ignored
        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $uow = $this->collector->getUnitOfWork();
        $this->assertSame(2, $uow['persisted']);
        $this->assertSame(1, $uow['updated']);
        $this->assertSame(1, $uow['removed']);
    }

    public function testReset(): void
    {
        $this->collector->addQuery('SELECT 1', [], 1.0);
        $this->collector->addHydratedEntity();
        $this->collector->addIdentityMapHit();
        $this->collector->setIdentityMapSize(10);
        $this->collector->addUnitOfWorkOperation('persisted');
        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $this->collector->reset();

        // After reset, everything should be zero/empty
        $this->assertSame(0, $this->collector->getQueryCount());
        $this->assertSame(0.0, $this->collector->getTotalTime());
        $this->assertSame([], $this->collector->getQueries());
        $this->assertSame(0, $this->collector->getHydratedEntities());
        $this->assertSame(0, $this->collector->getIdentityMapHits());
        $this->assertSame(0, $this->collector->getIdentityMapSize());
    }

    public function testQueryWithBacktrace(): void
    {
        $this->collector->addQuery('SELECT 1', [], 1.0, 'default', 'file.php:10 → MyClass::method()');
        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $queries = $this->collector->getQueries();
        $this->assertSame('file.php:10 → MyClass::method()', $queries[0]['backtrace']);
    }

    public function testNoWarningsWhenClean(): void
    {
        $this->collector->addQuery('SELECT 1', [], 10.0);
        $this->collector->addQuery('SELECT 2', [], 20.0);
        $this->collector->collect(new Request(), new Response());
        $this->collector->lateCollect();

        $this->assertFalse($this->collector->hasWarnings());
        $this->assertSame([], $this->collector->getDuplicateQueries());
        $this->assertSame([], $this->collector->getSlowQueries());
    }
}
