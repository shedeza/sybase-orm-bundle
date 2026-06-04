# Manual de Usuario — Caché

[← Anterior: Comandos](06-comandos.md) | [Índice](../README.md)

---

## Niveles de Caché

El bundle gestiona tres niveles de caché:

| Nivel | Descripción | Alcance |
|-------|-------------|---------|
| **Identity Map** | Mapa de entidades cargadas en el request actual | Per-request |
| **Metadata Cache** | Caché de metadatos de entidades (reflexión) | Memoria estática |
| **Second-Level Cache** | Caché persistente de resultados de queries | Cross-request |

## Configuración

### Habilitar caché

```yaml
sybase_orm:
    cache:
        enabled: true
        adapter: 'cache.app'      # Usar el adaptador de caché de Symfony
        default_ttl: 3600         # 1 hora
```

### Usar Redis

```yaml
sybase_orm:
    cache:
        enabled: true
        dsn: 'redis://localhost:6379'
        default_ttl: 7200         # 2 horas
```

### Usar el adaptador de Symfony

Si ya tienes configurado un pool de caché en Symfony:

```yaml
# config/packages/cache.yaml
framework:
    cache:
        pools:
            sybase.cache:
                adapter: cache.adapter.redis
                provider: 'redis://localhost:6379'

# config/packages/sybase_orm.yaml
sybase_orm:
    cache:
        enabled: true
        adapter: 'sybase.cache'
```

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
- Second-level cache (si está habilitada)
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
           adapter: 'cache.app'
           default_ttl: 3600
   ```

2. **Usar Redis** para caché compartida entre procesos:
   ```yaml
   sybase_orm:
       cache:
           enabled: true
           dsn: '%env(REDIS_URL)%'
   ```

3. **Ajustar el TTL** según tu caso de uso:
   - Datos que cambian frecuentemente: TTL bajo (300-600 segundos)
   - Datos de referencia estáticos: TTL alto (3600-86400 segundos)

4. **Limpiar en el deploy:**
   ```bash
   php bin/console sybase:cache:clear --env=prod
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
