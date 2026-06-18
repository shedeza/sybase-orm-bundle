# shedeza/sybase-orm-bundle

[![CI](https://github.com/shedeza/sybase-orm-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/shedeza/sybase-orm-bundle/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net/)
[![Symfony Version](https://img.shields.io/badge/symfony-6.x%20%7C%207.x-000000.svg)](https://symfony.com/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Packagist](https://img.shields.io/packagist/v/shedeza/sybase-orm-bundle.svg)](https://packagist.org/packages/shedeza/sybase-orm-bundle)

Symfony bundle providing full framework integration for the [shedeza/sybase-orm](https://github.com/shedeza/sybase-orm) library. It registers ORM services in the dependency injection container, provides console commands, Symfony Profiler integration, automatic repository autowiring, and Flex recipe support.

## Features

- **Full DI Integration** — All ORM services registered and autowireable out of the box
- **Multi-Connection Support** — Configure multiple named Sybase ASE connections
- **13 Console Commands** — Install, migrations (generate, execute, rollback, reset, fresh, preview, status), proxy generation, cache clear, schema validation, entity scaffolding, ORM info
- **Web Profiler** — Native ORM instrumentation with detailed metrics: queries, hydrations, identity map hits/misses, lazy loads, cache hits/misses, transactions, rollbacks, flush time
- **Redis Second-Level Cache** — Redis-based cache with circuit breaker pattern for fault tolerance
- **Repository Autowiring** — Custom repositories auto-registered via compiler pass
- **Symfony Flex** — Automatic bundle registration and configuration scaffolding
- **PHP 8.1+ Attributes** — Modern attribute-based command configuration

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | >= 8.1 |
| Symfony | 6.x or 7.x |
| shedeza/sybase-orm | ^3.6 |
| PHP Extension | pdo_dblib |
| Database | Sybase ASE |

## Installation

```bash
composer require shedeza/sybase-orm-bundle
```

### Symfony Flex (automatic)

If your project uses Symfony Flex, the bundle is registered automatically and configuration files are created. No manual steps needed.

### Without Flex (manual)

Add the bundle to `config/bundles.php`:

```php
return [
    // ...
    SybaseORM\Bundle\SybaseORMBundle::class => ['all' => true],
];
```

Then run the install command to scaffold configuration:

```bash
php bin/console sybase:install
```

## Quick Start

### 1. Configure your connection

Set the `DATABASE_URL` environment variable in your `.env` file:

```dotenv
DATABASE_URL="sybase://sa:password@127.0.0.1:5000/my_database?charset=UTF-8"
```

### 2. Create your configuration file

```yaml
# config/packages/sybase_orm.yaml
sybase_orm:
    connection:
        url: '%env(DATABASE_URL)%'
    entity_directories:
        - '%kernel.project_dir%/src/Entity'
```

### 3. Create and use a repository

```php
<?php
// src/Repository/ProductRepository.php

namespace App\Repository;

use App\Entity\Product;
use SybaseORM\ORM\EntityManagerInterface;
use SybaseORM\ORM\EntityRepository;

class ProductRepository extends EntityRepository
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct($entityManager, Product::class);
    }

    public function findActive(): array
    {
        return $this->findBy(['active' => true]);
    }
}
```

### 4. Inject the repository in your service

```php
use App\Repository\ProductRepository;

class ProductService
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {}

    public function findProduct(int $id): ?Product
    {
        return $this->productRepository->find($id);
    }

    public function getActiveProducts(): array
    {
        return $this->productRepository->findActive();
    }
}
```

Repositories linked via `#[Entity(repositoryClass: ...)]` are automatically registered for dependency injection. `EntityRepository` provides `find`, `findAll`, `findBy`, `save`, `delete`, `count`, `transactional`, and more out of the box.

## Configuration Reference

```yaml
# config/packages/sybase_orm.yaml
sybase_orm:

    # Single connection (simple setup)
    connection:
        url: '%env(DATABASE_URL)%'          # DSN URL (overrides individual params)
        host: '127.0.0.1'                   # Database server host
        port: 5000                          # Database server port
        database: 'my_database'             # Database name
        username: 'sa'                      # Authentication username
        password: 'secret'                  # Authentication password
        charset: 'UTF-8'                    # Connection character set
        persistent: false                   # Use persistent connections
        charset_conversion: false           # Enable UTF-8 ↔ ISO-8859-1 conversion
        read_only: false                    # Mark connection as read-only

    # Multiple named connections
    connections:
        default:
            url: '%env(DATABASE_URL)%'
        reporting:
            host: 'reporting-server'
            port: 5000
            database: 'reports'
            username: 'reader'
            password: 'secret'
            read_only: true

    # Entity mapping directories
    entity_directories:
        - '%kernel.project_dir%/src/Entity'

    # Directory for generated proxy classes
    proxy_directory: '%kernel.cache_dir%/sybase_orm/proxies'

    # Directory for migration files
    migrations_directory: '%kernel.project_dir%/sybase_ase/migrations'

    # File/directory permissions for generated files (proxies, metadata cache)
    file_permissions: 0o666
    directory_permissions: 0o777

    # Cache configuration (second-level cache with circuit breaker)
    cache:
        enabled: true
        adapter: redis                      # Cache adapter: 'redis' or null
        default_ttl: 3600                   # Default cache TTL in seconds
        prefix: 'sybase_orm:'              # Key prefix for Redis entries
        failure_threshold: 3                # Failures before circuit breaker opens
        cooldown_seconds: 60                # Seconds before retrying after circuit opens

    # Redis connection (when cache.adapter is 'redis')
    redis:
        host: '127.0.0.1'
        port: 6379
        password: null
        database: 0
        timeout: 2.0
        dsn: null                           # Full DSN overrides host/port if set
```

> **Note:** Use either `connection` (single) or `connections` (multiple named), not both. When `url` is provided, individual parameters (host, port, etc.) are ignored.

## Console Commands

| Command | Description |
|---------|-------------|
| `sybase:install` | Scaffolds configuration files and registers the bundle |
| `sybase:make:entity` | Generates a new entity class with mapping attributes |
| `sybase:orm:info` | Displays information about mapped entities |
| `sybase:migrate` | Executes all pending migrations |
| `sybase:migrate:status` | Shows current migration status |
| `sybase:migrate:generate` | Generates a migration from entity/schema diff |
| `sybase:migrate:rollback` | Rolls back the last migration batch |
| `sybase:migrate:reset` | Rolls back all migrations |
| `sybase:migrate:fresh` | Drops all tables and re-runs all migrations |
| `sybase:migrate:preview` | Previews SQL for pending migrations without executing |
| `sybase:schema:validate` | Validates entity mapping against the database |
| `sybase:cache:clear` | Clears metadata and entity caches |
| `sybase:proxy:generate` | Generates lazy-loading proxy classes |

## Registered Services

The following services are available for autowiring:

| Interface | Implementation |
|-----------|---------------|
| `EntityManagerInterface` | `EntityManager` |
| `ConnectionManagerInterface` | `ConnectionManager` |
| `MetadataReaderInterface` | `MetadataReader` |
| `DialectInterface` | `SybaseDialect` |
| `UnitOfWorkInterface` | `UnitOfWork` |
| `IdentityMapInterface` | `IdentityMap` |
| `HydratorInterface` | `Hydrator` |
| `TypeCasterInterface` | `TypeCaster` |
| `CacheManagerInterface` | `CacheManager` |

Additional services: `HookDispatcher`, `ProxyGenerator`, `MigrationManager`, `EntityManagerRegistry`.

Custom entity repositories annotated with `#[Entity(repositoryClass: ...)]` are automatically registered for autowiring.

## Web Profiler Integration

In `dev` environment, the bundle uses **native ORM instrumentation** (since v2.0) to collect metrics without decorator overhead. The `SybaseQueryCollector` reads from `InstrumentationCollector` and displays in the Symfony debug toolbar and profiler panel:

- Number of queries executed per request and total query time
- Individual query details (SQL, parameters, timing, connection name)
- Hydration count and collection loads
- Identity map hits and misses
- Lazy load count
- Cache hits, misses, and writes (second-level cache)
- Transaction and rollback count
- Flush time
- Duplicate query detection
- Slow query highlighting

In production, `NullInstrumentation` is used (zero overhead).

## Testing

```bash
# Run tests
vendor/bin/phpunit

# Static analysis
vendor/bin/phpstan analyse

# Code style
vendor/bin/php-cs-fixer fix --dry-run --diff
```

## CI Matrix

The bundle is tested against:
- PHP: 8.1, 8.2, 8.3, 8.4
- Symfony: 6.x, 7.x (7.x requires PHP ≥ 8.2)

## Documentation

Full documentation is available in the [`docs/`](docs/) directory:

- [📖 User Manual](docs/manual-usuario/) — Installation, configuration, and usage guide
- [🔧 Technical Manual](docs/manual-tecnico/) — Architecture, internals, and extension points
- [🚀 Operations Manual](docs/manual-operacion/) — Deployment, monitoring, and troubleshooting

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Commit your changes (`git commit -am 'Add my feature'`)
4. Push to the branch (`git push origin feature/my-feature`)
5. Open a Pull Request

Please ensure all tests pass and code follows the existing style (PHP-CS-Fixer).

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
