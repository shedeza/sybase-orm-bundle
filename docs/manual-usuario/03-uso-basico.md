# Manual de Usuario — Uso Básico

[← Anterior: Configuración](02-configuracion.md) | [Índice](../README.md) | [Siguiente: Repositorios →](04-repositorios.md)

---

## Enfoque basado en Repositorios

El acceso a datos se realiza exclusivamente a través de **repositorios específicos** para cada entidad. Cada repositorio extiende `EntityRepository`, que proporciona todos los métodos CRUD y de consulta necesarios. Los servicios de negocio nunca interactúan directamente con el `EntityManager`.

Este patrón ofrece:
- **Separación de responsabilidades** — la lógica de acceso a datos queda aislada
- **Herencia de métodos base** — `find`, `findAll`, `save`, `delete`, etc. vienen listos
- **Reutilización** — los métodos del repositorio se comparten entre servicios
- **Testabilidad** — se puede mockear el repositorio fácilmente en tests unitarios

## Definir la Entidad

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

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getPrice(): float { return $this->price; }
    public function setPrice(float $price): void { $this->price = $price; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): void { $this->active = $active; }
}
```

## Crear el Repositorio

Los repositorios extienden `EntityRepository` y pasan la clase de entidad al constructor padre:

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

    // Métodos específicos del dominio:

    public function findActive(): array
    {
        return $this->findBy(['active' => true]);
    }

    public function findByCategory(int $categoryId): array
    {
        return $this->findBy(['category_id' => $categoryId]);
    }

    public function findExpensive(float $minPrice): array
    {
        return $this->query(
            'SELECT e FROM Product e WHERE e.price >= :min AND e.active = :active',
            ['min' => $minPrice, 'active' => true]
        );
    }
}
```

> **Nota:** No necesitas implementar `find()`, `findAll()`, `findBy()`, `save()`, `delete()`, etc. — todos vienen heredados de `EntityRepository`.

## Métodos Heredados de EntityRepository

Al extender `EntityRepository`, tu repositorio obtiene automáticamente:

| Método | Descripción |
|--------|-------------|
| `find($id)` | Busca por clave primaria |
| `findOrFail($id)` | Busca por clave primaria o lanza excepción |
| `findAll()` | Obtiene todas las entidades |
| `findBy($criteria, $orderBy, $limit, $offset)` | Busca por criterios con orden y paginación |
| `findOneBy($criteria)` | Busca una entidad por criterios |
| `findOneByOrFail($criteria)` | Busca una o lanza excepción |
| `save($entity)` | Persiste y hace flush |
| `saveMany($entities)` | Persiste múltiples entidades en una transacción |
| `delete($entity)` | Elimina y hace flush |
| `deleteMany($entities)` | Elimina múltiples entidades en una transacción |
| `persist($entity)` | Registra para inserción/actualización sin flush |
| `flush()` | Ejecuta todas las operaciones pendientes |
| `merge($entity)` | Merge de entidad detached |
| `count($criteria)` | Cuenta entidades por criterios |
| `exists($criteria)` | Verifica si existe al menos una entidad |
| `query($oql, $params)` | Ejecuta OQL y devuelve resultados |
| `queryIterator($oql, $params)` | Ejecuta OQL con Generator (memoria eficiente) |
| `queryCached($oql, $params, $ttl)` | Query con caché de segundo nivel |
| `queryScalar($oql, $params)` | Devuelve un valor escalar |
| `executeUpdate($oql, $params)` | Ejecuta UPDATE/DELETE OQL |
| `createQueryBuilder()` | Crea un QueryBuilder para la entidad |
| `transactional($callback)` | Ejecuta un callable en transacción |
| `refresh($entity)` | Recarga la entidad desde la base de datos |

## Operaciones CRUD

### Inyectar el repositorio en tu servicio

```php
<?php
// src/Service/ProductService.php

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;

class ProductService
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {}
}
```

El `RepositoryAutowiringCompilerPass` registra automáticamente los repositorios asociados con `#[Entity(repositoryClass: ...)]`. No necesitas configurar nada en `services.yaml`.

### Buscar por ID

```php
public function getProduct(int $id): ?Product
{
    return $this->productRepository->find($id);
}

// O lanzar excepción si no existe:
public function getProductOrFail(int $id): Product
{
    return $this->productRepository->findOrFail($id);
}
```

### Buscar todos

```php
public function getAllProducts(): array
{
    return $this->productRepository->findAll();
}
```

### Buscar por criterios

```php
public function getActiveProducts(): array
{
    return $this->productRepository->findActive();
}

// Con ordenamiento y paginación:
public function getProductsPaginated(int $page, int $size): array
{
    return $this->productRepository->findBy(
        ['active' => true],
        ['name' => 'ASC'],
        $size,
        ($page - 1) * $size
    );
}
```

### Crear una entidad

```php
public function createProduct(string $name, float $price): Product
{
    $product = new Product();
    $product->setName($name);
    $product->setPrice($price);
    $product->setActive(true);

    $this->productRepository->save($product);

    return $product;
}
```

### Actualizar una entidad

```php
public function updatePrice(int $productId, float $newPrice): void
{
    $product = $this->productRepository->findOrFail($productId);
    $product->setPrice($newPrice);
    $this->productRepository->save($product);
}
```

### Eliminar una entidad

```php
public function deleteProduct(int $productId): void
{
    $product = $this->productRepository->findOrFail($productId);
    $this->productRepository->delete($product);
}
```

## Operaciones en Lote

```php
public function importProducts(array $data): void
{
    $products = [];

    foreach ($data as $item) {
        $product = new Product();
        $product->setName($item['name']);
        $product->setPrice($item['price']);
        $products[] = $product;
    }

    // Una sola transacción para todas las inserciones
    $this->productRepository->saveMany($products);
}

public function deactivateProducts(array $productIds): void
{
    $products = $this->productRepository->findBy(['id' => $productIds]);

    foreach ($products as $product) {
        $product->setActive(false);
    }

    $this->productRepository->flush();
}
```

## Transacciones

Para operaciones que requieren atomicidad entre múltiples repositorios:

```php
class OrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly ProductRepository $productRepository,
    ) {}

    public function placeOrder(int $productId, int $quantity): Order
    {
        return $this->orderRepository->transactional(function () use ($productId, $quantity) {
            $product = $this->productRepository->findOrFail($productId);

            $order = new Order();
            $order->setProduct($product);
            $order->setQuantity($quantity);
            $order->setTotal($product->getPrice() * $quantity);

            $this->orderRepository->save($order);

            return $order;
        });
    }
}
```

## Conteo y Existencia

```php
// Contar productos activos
$count = $this->productRepository->count(['active' => true]);

// Verificar si existe un producto con ese nombre
$exists = $this->productRepository->exists(['name' => 'Widget Pro']);
```

## QueryBuilder

Para queries más complejas, usa el QueryBuilder:

```php
class ProductRepository extends EntityRepository
{
    // ...

    public function findWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder();

        if (isset($filters['minPrice'])) {
            $qb->where('e.price >= :minPrice', ['minPrice' => $filters['minPrice']]);
        }

        if (isset($filters['category'])) {
            $qb->andWhere('e.category_id = :cat', ['cat' => $filters['category']]);
        }

        $qb->orderBy('e.name', 'ASC');

        if (isset($filters['limit'])) {
            $qb->setMaxResults($filters['limit']);
        }

        return $qb->getResult();
    }
}
```

## Identity Map

El ORM mantiene un mapa de identidad que asegura que cada entidad se represente por una única instancia PHP durante el ciclo de vida del request:

```php
$product1 = $this->productRepository->find(42);
$product2 = $this->productRepository->find(42);

// Misma instancia — no hay consulta duplicada a la DB
assert($product1 === $product2); // true
```

## Queries Directos (SQL)

Para reportes o consultas que no encajan en el patrón de entidades, crea un repositorio especializado con `ConnectionManagerInterface`:

```php
<?php
// src/Repository/ReportRepository.php

namespace App\Repository;

use SybaseORM\Connection\ConnectionManagerInterface;

class ReportRepository
{
    public function __construct(
        private readonly ConnectionManagerInterface $connection,
    ) {}

    public function getTotalSales(int $year): float
    {
        $stmt = $this->connection->executeQuery(
            'SELECT SUM(total) as total FROM orders WHERE year = ?',
            [$year]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return (float) ($row['total'] ?? 0);
    }
}
```

## Conversión de Charset

Si tu base de datos Sybase ASE usa ISO-8859-1 pero tu aplicación trabaja en UTF-8:

```yaml
sybase_orm:
    connection:
        url: '%env(DATABASE_URL)%'
        charset_conversion: true
```

Esto convierte automáticamente:
- **Escritura (PHP → DB):** UTF-8 → ISO-8859-1
- **Lectura (DB → PHP):** ISO-8859-1 → UTF-8

---

[← Anterior: Configuración](02-configuracion.md) | [Índice](../README.md) | [Siguiente: Repositorios →](04-repositorios.md)
