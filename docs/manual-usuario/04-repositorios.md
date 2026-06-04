# Manual de Usuario — Repositorios

[← Anterior: Uso Básico](03-uso-basico.md) | [Índice](../README.md) | [Siguiente: Migraciones →](05-migraciones.md)

---

## Repositorios Custom

Los repositorios custom encapsulan la lógica de consultas específicas para una entidad. El bundle soporta el registro automático de repositorios gracias al `RepositoryAutowiringCompilerPass`.

## Definir un Repositorio Custom

### 1. Crear la clase del repositorio

```php
<?php
// src/Repository/ProductRepository.php

namespace App\Repository;

use App\Entity\Product;
use SybaseORM\ORM\EntityManagerInterface;

class ProductRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function findActive(): array
    {
        return $this->entityManager->findBy(Product::class, [
            'active' => true,
        ]);
    }

    public function findByCategory(int $categoryId): array
    {
        return $this->entityManager->findBy(Product::class, [
            'category_id' => $categoryId,
        ]);
    }

    public function findExpensive(float $minPrice): array
    {
        return $this->entityManager->findBy(Product::class, [
            'price' => ['>=', $minPrice],
        ]);
    }
}
```

### 2. Asociar el repositorio a la entidad

Usa el atributo `#[Entity]` con el parámetro `repositoryClass`:

```php
<?php
// src/Entity/Product.php

namespace App\Entity;

use App\Repository\ProductRepository;
use SybaseORM\Mapping\Entity;
use SybaseORM\Mapping\Column;
use SybaseORM\Mapping\Id;

#[Entity(table: 'products', repositoryClass: ProductRepository::class)]
class Product
{
    #[Id]
    #[Column(name: 'id', type: 'int')]
    private ?int $id = null;

    #[Column(name: 'name', type: 'string')]
    private string $name;

    #[Column(name: 'price', type: 'decimal')]
    private float $price;

    #[Column(name: 'active', type: 'bool')]
    private bool $active = true;

    // Getters y setters...
}
```

## Autowiring Automático

El `RepositoryAutowiringCompilerPass` del bundle escanea automáticamente las entidades en los directorios configurados y registra cada `repositoryClass` como un servicio autowireable en el contenedor de Symfony.

Esto significa que puedes inyectar directamente el repositorio en cualquier servicio:

```php
<?php
// src/Service/CatalogService.php

namespace App\Service;

use App\Repository\ProductRepository;

class CatalogService
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {}

    public function getActiveProducts(): array
    {
        return $this->productRepository->findActive();
    }
}
```

No necesitas registrar el servicio manualmente en `services.yaml`.

## Cómo Funciona Internamente

El compiler pass realiza las siguientes operaciones en tiempo de compilación:

1. Lee los directorios configurados en `sybase_orm.entity_directories`
2. Descubre todas las clases con el atributo `#[Entity]`
3. Lee los metadatos de cada entidad para obtener `repositoryClass`
4. Registra cada repositorio custom como servicio factory usando `EntityManagerRegistry::getRepository()`
5. El servicio es private (no accesible directamente desde el contenedor, solo por autowiring)

## Acceder al Repositorio via EntityManagerRegistry

También puedes obtener repositorios programáticamente:

```php
use SybaseORM\ORM\EntityManagerRegistry;
use App\Entity\Product;

class SomeService
{
    public function __construct(
        private readonly EntityManagerRegistry $registry,
    ) {}

    public function doSomething(): void
    {
        $repo = $this->registry->getRepository(Product::class);
        $products = $repo->findActive();
    }
}
```

## Repositorios y Multi-Conexión

Cuando tienes múltiples conexiones, los repositorios se asocian a la conexión que gestiona la entidad. Por defecto, los repositorios auto-registrados usan la conexión principal (la primera definida en la configuración).

Si necesitas un repositorio que use una conexión específica:

```php
class ReportRepository
{
    public function __construct(
        private readonly EntityManagerRegistry $registry,
    ) {}

    public function findReports(): array
    {
        $em = $this->registry->getManager('reporting');
        return $em->findAll(Report::class);
    }
}
```

## Buenas Prácticas

1. **Un repositorio por entidad**: Cada entidad debería tener su propio repositorio con queries específicas.
2. **Nombres descriptivos**: Usa métodos como `findActiveByCategory()` en lugar de `getList()`.
3. **No inyectar el contenedor**: Usa siempre autowiring para las dependencias del repositorio.
4. **Queries complejas en el repositorio**: Mantén la lógica de consultas fuera de los servicios de negocio.

---

[← Anterior: Uso Básico](03-uso-basico.md) | [Índice](../README.md) | [Siguiente: Migraciones →](05-migraciones.md)
