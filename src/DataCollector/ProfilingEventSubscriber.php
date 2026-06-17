<?php

declare(strict_types=1);

namespace SybaseORM\Bundle\DataCollector;

use SybaseORM\Hook\EventSubscriberInterface;

/**
 * Subscribes to entity lifecycle events and records UnitOfWork operations
 * in the SybaseQueryCollector for the Symfony Profiler.
 */
final class ProfilingEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SybaseQueryCollector $collector,
    ) {}

    public function getSubscribedEvents(): array
    {
        return ['PostPersist', 'PostUpdate', 'PostRemove'];
    }

    public function onEvent(object $entity, string $hookType): void
    {
        match ($hookType) {
            'PostPersist' => $this->collector->addUnitOfWorkOperation('persisted'),
            'PostUpdate' => $this->collector->addUnitOfWorkOperation('updated'),
            'PostRemove' => $this->collector->addUnitOfWorkOperation('removed'),
            default => null,
        };
    }
}
