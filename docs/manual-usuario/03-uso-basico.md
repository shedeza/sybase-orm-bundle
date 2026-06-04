# Manual de Usuario — Uso Básico

[← Anterior: Configuración](02-configuracion.md) | [Índice](../README.md) | [Siguiente: Repositorios →](04-repositorios.md)

---

## EntityManager

El `EntityManager` es el servicio principal para interactuar con la base de datos. Se inyecta automáticamente en cualquier servicio Symfony a través de su interfaz:

```php
use SybaseORM\ORM\EntityManagerInterface;

class ProductService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}
}
```

## Operaciones CRUD

### Buscar por ID (find)

```php
// Buscar un producto por su clave primaria
$product = $this->entityManager->find(Product::class, 42);

if ($product === null) {
    throw new \RuntimeException('Producto no encontrado');
}
```

### Buscar todos (findAll)

```php
// Obtener todas las categorías
$categories = $this->entityManager->findAll(Category::class);
```

### Buscar por criterios (findBy)

```php
// Buscar productos activos con precio mayor a 100
$products = $this->entityManager->findBy(Product::class, [
    'active' => true,
    'price' => ['>', 100],
]);
```

### Buscar uno por criterios (findOneBy)

```php
// Buscar un usuario por email
$user = $this->entityManager->findOneBy(User::class, [
    'email' => 'admin@example.com',
]);
```

### Crear (persist + flush)

```php
$product = new Product();
$product->setName('Nuevo Producto');
$product->setPrice(29.99);
$product->setActive(true);

// Marcar para persistir
$this->entityManager->persist($product);

// Ejecutar las operaciones pendientes en la base de datos
$this->entityManager->flush();

// Ahora $product tiene su ID asignado
echo $product->getId(); // e.g., 123
```

### Actualizar (modificar + flush)

```php
$product = $this->entityManager->find(Product::class, 42);
$product->setPrice(39.99);

// No es necesario llamar a persist() para entidades ya gestionadas
$this->entityManager->flush();
```

### Eliminar (remove + flush)

```php
$product = $this->entityManager->find(Product::class, 42);

$this->entityManager->remove($product);
$this->entityManager->flush();
```

## UnitOfWork

El `EntityManager` utiliza internamente un `UnitOfWork` que acumula todas las operaciones (inserts, updates, deletes) y las ejecuta en una sola transacción al llamar a `flush()`.

Esto significa que puedes realizar múltiples operaciones antes de llamar a `flush()`:

```php
// Múltiples operaciones en una sola transacción
$product1 = new Product();
$product1->setName('Producto A');
$this->entityManager->persist($product1);

$product2 = new Product();
$product2->setName('Producto B');
$this->entityManager->persist($product2);

$oldProduct = $this->entityManager->find(Product::class, 1);
$this->entityManager->remove($oldProduct);

// Todo se ejecuta en una sola transacción
$this->entityManager->flush();
```

## Identity Map

El ORM mantiene un mapa de identidad que asegura que cada entidad se represente por una única instancia PHP durante el ciclo de vida del request:

```php
$product1 = $this->entityManager->find(Product::class, 42);
$product2 = $this->entityManager->find(Product::class, 42);

// Misma instancia — no hay consulta duplicada a la DB
assert($product1 === $product2); // true
```

## Ejecutar Queries Directos

Si necesitas ejecutar SQL directamente:

```php
use SybaseORM\Connection\ConnectionManagerInterface;

class ReportService
{
    public function __construct(
        private readonly ConnectionManagerInterface $connection,
    ) {}

    public function getTotalSales(): float
    {
        $stmt = $this->connection->executeQuery(
            'SELECT SUM(total) as total FROM orders WHERE year = ?',
            [2024]
        );

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return (float) ($row['total'] ?? 0);
    }
}
```

## Uso con EntityManagerRegistry

Para aplicaciones con múltiples conexiones, utiliza `EntityManagerRegistry` para acceder a managers específicos:

```php
use SybaseORM\ORM\EntityManagerRegistry;

class MultiDbService
{
    public function __construct(
        private readonly EntityManagerRegistry $registry,
    ) {}

    public function syncData(): void
    {
        // Manager de la conexión principal
        $defaultEm = $this->registry->getManager('default');

        // Manager de la conexión de reporting
        $reportingEm = $this->registry->getManager('reporting');

        $products = $defaultEm->findAll(Product::class);
        // ... procesar y guardar en reporting
    }
}
```

## Conexiones de Solo Lectura

Si configuras una conexión con `read_only: true`, el EntityManager asociado bloqueará operaciones de escritura:

```php
// En una conexión read_only, esto lanzará una excepción
$em = $this->registry->getManager('reporting');
$em->persist($newEntity); // ❌ ReadOnlyException
$em->flush();             // ❌ ReadOnlyException
```

Esto es útil para conexiones a réplicas de lectura o bases de datos de reporting.

## Conversión de Charset

Si tu base de datos Sybase ASE usa ISO-8859-1 pero tu aplicación trabaja en UTF-8, activa la conversión:

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
