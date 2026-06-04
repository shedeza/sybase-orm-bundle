# Manual de Usuario — Repositorios

[← Anterior: Uso Básico](03-uso-basico.md) | [Índice](../README.md) | [Siguiente: Migraciones →](05-migraciones.md)

---

## Patrón de Repositorio

El **repositorio** es el punto central de acceso a datos para cada entidad. Todos los repositorios extienden `EntityRepository`, que proporciona métodos CRUD completos, queries OQL, transacciones y más. Los servicios de negocio inyectan el repositorio concreto y nunca interactúan con el `EntityManager` directamente.

## Estructura de un Repositorio

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

    // --- Métodos específicos del dominio ---

    public function findActive(): array
    {
        return $this->findBy(['active' => true]);
    }

    public function findByCategory(int $categoryId, ?string $orderBy = 'name'): array
    {
        return $this->findBy(
            ['category_id' => $categoryId, 'active' => true],
            [$orderBy => 'ASC']
        );
    }

    public function findExpensive(float $minPrice): array
    {
        return $this->query(
            'SELECT e FROM Product e WHERE e.price >= :min AND e.active = :active',
            ['min' => $minPrice, 'active' => true]
        );
    }

    public function deactivate(Product $product): void
    {
        $product->setActive(false);
        $this->flush();
    }

    public function countActive(): int
    {
        return $this->count(['active' => true]);
    }
}
```

## Asociar el Repositorio a la Entidad

Usa el atributo `#[Entity]` con `repositoryClass`:

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

El `RepositoryAutowiringCompilerPass` escanea automáticamente las entidades y registra cada `repositoryClass` como servicio autowireable. Inyecta el repositorio directamente:

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

    public function getProductCount(): int
    {
        return $this->productRepository->countActive();
    }
}
```

No necesitas registrar el servicio manualmente en `services.yaml`.

## Uso en Controladores

```php
<?php
// src/Controller/ProductController.php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/products')]
class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->productRepository->findActive());
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $product = $this->productRepository->findOrFail($id);
        return $this->json($product);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();

        $product = new Product();
        $product->setName($data['name']);
        $product->setPrice($data['price']);

        $this->productRepository->save($product);

        return $this->json($product, 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $product = $this->productRepository->findOrFail($id);
        $data = $request->toArray();

        if (isset($data['name'])) {
            $product->setName($data['name']);
        }
        if (isset($data['price'])) {
            $product->setPrice($data['price']);
        }

        $this->productRepository->save($product);

        return $this->json($product);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $product = $this->productRepository->findOrFail($id);
        $this->productRepository->delete($product);

        return $this->json(null, 204);
    }
}
```

## Métodos de Consulta Avanzados

### findBy con ordenamiento y paginación

```php
// Productos activos, ordenados por nombre, página 2 con 20 por página
$products = $this->productRepository->findBy(
    ['active' => true],
    ['name' => 'ASC'],
    20,    // limit
    20     // offset (página 2)
);
```

### Criterios con arrays (IN)

```php
// Buscar productos por múltiples IDs
$products = $this->productRepository->findBy([
    'id' => [1, 2, 3, 4, 5],
]);

// Buscar por múltiples categorías
$products = $this->productRepository->findBy([
    'category_id' => [10, 20, 30],
    'active' => true,
]);
```

### Conteo y existencia

```php
$totalActive = $this->productRepository->count(['active' => true]);

$exists = $this->productRepository->exists(['name' => 'Widget Pro']);
```

### QueryBuilder

```php
class ProductRepository extends EntityRepository
{
    // ...

    public function search(string $term, ?int $categoryId = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder();

        $qb->where('e.name LIKE :term', ['term' => "%{$term}%"]);
        $qb->andWhere('e.active = :active', ['active' => true]);

        if ($categoryId !== null) {
            $qb->andWhere('e.category_id = :cat', ['cat' => $categoryId]);
        }

        $qb->orderBy('e.name', 'ASC');
        $qb->setMaxResults($limit);

        return $qb->getResult();
    }
}
```

### Queries OQL directos

```php
class ProductRepository extends EntityRepository
{
    // ...

    public function findTopSelling(int $limit = 10): array
    {
        return $this->query(
            'SELECT e FROM Product e ORDER BY e.sales_count DESC',
            [],
        );
    }

    public function getTotalRevenue(): float
    {
        return (float) $this->queryScalar(
            'SELECT SUM(e.price * e.quantity_sold) FROM Product e WHERE e.active = :active',
            ['active' => true]
        );
    }
}
```

### Iterador para grandes conjuntos de datos

```php
class ProductRepository extends EntityRepository
{
    // ...

    public function exportAll(): \Generator
    {
        return $this->queryIterator('SELECT e FROM Product e ORDER BY e.id ASC');
    }
}

// En el servicio:
foreach ($this->productRepository->exportAll() as $product) {
    // Procesa uno a uno sin cargar todo en memoria
    $this->export($product);
}
```

### Queries con caché

```php
class ProductRepository extends EntityRepository
{
    // ...

    public function findFeatured(): array
    {
        return $this->queryCached(
            'SELECT e FROM Product e WHERE e.featured = :featured AND e.active = :active',
            ['featured' => true, 'active' => true],
            1800 // TTL: 30 minutos
        );
    }
}
```

## Transacciones

### Transacción simple

```php
class OrderRepository extends EntityRepository
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct($entityManager, Order::class);
    }

    public function createWithItems(Order $order, array $items): void
    {
        $this->transactional(function () use ($order, $items) {
            $this->save($order);

            foreach ($items as $item) {
                $item->setOrder($order);
                $this->getEntityManager()->persist($item);
            }

            $this->flush();
        });
    }
}
```

### Transacción manual

```php
public function transferStock(Product $from, Product $to, int $quantity): void
{
    $this->beginTransaction();

    try {
        $from->decreaseStock($quantity);
        $to->increaseStock($quantity);
        $this->flush();
        $this->commit();
    } catch (\Throwable $e) {
        $this->rollback();
        throw $e;
    }
}
```

## Repositorios y Multi-Conexión

Para repositorios que usan una conexión específica, inyecta `EntityManagerRegistry`:

```php
<?php
// src/Repository/ReportingProductRepository.php

namespace App\Repository;

use App\Entity\Product;
use SybaseORM\ORM\EntityManagerRegistry;
use SybaseORM\ORM\EntityRepository;

class ReportingProductRepository extends EntityRepository
{
    public function __construct(EntityManagerRegistry $registry)
    {
        parent::__construct($registry->getManager('reporting'), Product::class);
    }

    public function findAllForReporting(): array
    {
        return $this->findAll();
    }
}
```

## Refrescar una Entidad

Si necesitas recargar una entidad desde la base de datos descartando cambios en memoria:

```php
$product = $this->productRepository->find(42);
$product->setPrice(999.99); // Cambio local

$this->productRepository->refresh($product); // Recarga de la DB
// $product->getPrice() vuelve al valor original
```

## Cómo Funciona Internamente

El compiler pass realiza en tiempo de compilación:

1. Lee los directorios configurados en `sybase_orm.entity_directories`
2. Descubre todas las clases con el atributo `#[Entity]`
3. Lee `repositoryClass` de los metadatos de cada entidad
4. Registra cada repositorio como servicio en el contenedor
5. El servicio queda disponible para autowiring sin configuración manual

## Buenas Prácticas

1. **Un repositorio por entidad** — Cada entidad debe tener su propio repositorio que extienda `EntityRepository`.
2. **Nunca inyectar `EntityManager` en servicios** — Siempre usa el repositorio correspondiente.
3. **Nombres descriptivos** — Usa métodos como `findActiveByCategory()`, no `getList()`.
4. **Lógica de consultas en el repositorio** — Los servicios y controladores no deben construir queries.
5. **Usa `findOrFail` cuando la entidad es requerida** — Evita checks manuales de null.
6. **Usa `transactional()` para operaciones atómicas** — Garantiza rollback automático en caso de error.
7. **Usa `queryIterator()` para exports masivos** — No cargues miles de entidades en memoria.

---

[← Anterior: Uso Básico](03-uso-basico.md) | [Índice](../README.md) | [Siguiente: Migraciones →](05-migraciones.md)
