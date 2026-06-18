# Manual de Usuario — Caché

[← Anterior: Comandos](06-comandos.md) | [Índice](../README.md)

---

## Niveles de Caché

El bundle gestiona tres niveles de caché:

| Nivel | Descripción | Alcance |
|-------|-------------|---------|
| **Identity Map** | Mapa de entidades cargadas en el request actual | Per-request |
| **Metadata Cache** | Caché de metadatos de entidades (reflexión) | Memoria estática |
| **Second-Level Cache** | Caché persistente Redis con circuit breaker | Cross-request |

## Configuración

### Configuración completa con Redis

```yaml
sybase_orm:
    cache:
        enabled: true
        adapter: redis
        default_ttl: 3600
        prefix: 'sybase_orm:'
        failure_threshold: 3
        cooldown_seconds: 60

    redis:
        host: '127.0.0.1'
        port: 6379
        password: null
        database: 0
        timeout: 2.0
        dsn: null
```

### Parámetros de caché

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `enabled` | boolean | `false` | Habilita/deshabilita la caché de segundo nivel |
| `adapter` | string | `null` | Tipo de adaptador: `redis` o `null` |
| `default_ttl` | integer | `3600` | TTL por defecto en segundos |
| `prefix` | string | `'sybase_orm:'` | Prefijo para claves en Redis |
| `failure_threshold` | integer | `3` | Fallos consecutivos antes de abrir el circuit breaker |
| `cooldown_seconds` | integer | `60` | Segundos de espera antes de reintentar tras apertura |

### Parámetros de Redis

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `host` | string | `'127.0.0.1'` | Host del servidor Redis |
| `port` | integer | `6379` | Puerto del servidor |
| `password` | string | `null` | Contraseña de autenticación |
| `database` | integer | `0` | Índice de base de datos Redis (0-15) |
| `timeout` | float | `2.0` | Timeout de conexión en segundos |
| `dsn` | string | `null` | DSN completo (override de host/port) |

### Usar DSN de Redis

Si prefieres una URL completa:

```yaml
sybase_orm:
    cache:
        enabled: true
        adapter: redis
        default_ttl: 3600

    redis:
        dsn: 'redis://:mi_password@redis-server:6379/2'
```

Cuando se proporciona `dsn`, los valores de `host`, `port`, `password` y `database` se extraen automáticamente del DSN.

## Circuit Breaker

El circuit breaker protege la aplicación cuando Redis no está disponible, evitando timeouts repetidos que degraden el rendimiento:

### Cómo funciona

```
Estado CERRADO (normal)
    → Operaciones de caché van a Redis
    → Si falla → incrementar contador de fallos

Tras N fallos consecutivos (failure_threshold)
    → Estado ABIERTO
    → Operaciones de caché se omiten (sin intentar conexión)
    → La aplicación sigue funcionando sin caché

Después de cooldown_seconds
    → Estado SEMI-ABIERTO
    → Se intenta una operación de prueba
    → Si éxito → CERRADO (caché restaurada)
    → Si falla → ABIERTO (reiniciar cooldown)
```

### Configuración recomendada

| Escenario | `failure_threshold` | `cooldown_seconds` |
|-----------|--------------------|--------------------|
| Redis local confiable | 5 | 30 |
| Redis remoto / cloud | 3 | 60 |
| Redis en cluster con failover | 2 | 120 |

## Identity Map

El Identity Map se gestiona automáticamente y no requiere configuración. Garantiza que:

- Cada entidad tiene una única instancia PHP por request
- No se realizan queries duplicadas para la misma entidad
- Los cambios en una entidad son visibles en todas las referencias

Se limpia automáticamente al finalizar el request.

## Metadata Cache

La caché de metadatos almacena los resultados de la reflexión de atributos PHP (parseo de `#[Entity]`, `#[Column]`, etc.) para evitar repetir el proceso en cada request.

En **desarrollo**, la caché de metadatos en memoria se usa únicamente durante el request actual.

En **producción**, se recomienda pre-generar los proxies para que la caché de metadatos se persista en archivos:

```bash
php bin/console sybase:proxy:generate
```

## Limpiar la Caché

### Desde la consola

```bash
php bin/console sybase:cache:clear
```

Esto limpia:
- Identity map (todas las entidades en memoria)
- Second-level cache en Redis (si está habilitada)
- Metadata memory cache (caché estática)

### Desde código

```php
use SybaseORM\Cache\CacheManagerInterface;

class AdminService
{
    public function __construct(
        private readonly CacheManagerInterface $cacheManager,
    ) {}

    public function invalidateCache(): void
    {
        $this->cacheManager->clear();
    }
}
```

## Caché en Producción

Recomendaciones para entornos productivos:

1. **Habilitar siempre** la caché en producción:
   ```yaml
   sybase_orm:
       cache:
           enabled: true
           adapter: redis
           default_ttl: 3600
           prefix: 'sybase_orm:'
           failure_threshold: 3
           cooldown_seconds: 60
       redis:
           host: '%env(REDIS_HOST)%'
           port: '%env(int:REDIS_PORT)%'
           password: '%env(REDIS_PASSWORD)%'
   ```

2. **Ajustar el TTL** según tu caso de uso:
   - Datos que cambian frecuentemente: TTL bajo (300-600 segundos)
   - Datos de referencia estáticos: TTL alto (3600-86400 segundos)

3. **Limpiar en el deploy:**
   ```bash
   php bin/console sybase:cache:clear --env=prod
   ```

4. **Monitorear el circuit breaker** en los logs — si se activa frecuentemente, revisar la conectividad Redis.

## Queries con Caché

Desde los repositorios, puedes ejecutar queries que se almacenan en la caché de segundo nivel:

```php
class ProductRepository extends EntityRepository
{
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

## Deshabilitar Caché

Para desarrollo o debugging:

```yaml
sybase_orm:
    cache:
        enabled: false
```

Con la caché deshabilitada, el `CacheManager` sigue funcionando pero no persiste datos entre requests (solo el Identity Map por request funciona normalmente).

---

[← Anterior: Comandos](06-comandos.md) | [Índice](../README.md)
