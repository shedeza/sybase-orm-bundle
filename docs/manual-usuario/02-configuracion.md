# Manual de Usuario — Configuración

[← Anterior: Instalación](01-instalacion.md) | [Índice](../README.md) | [Siguiente: Uso Básico →](03-uso-basico.md)

---

## Archivo de Configuración

La configuración del bundle se define en `config/packages/sybase_orm.yaml` bajo la clave `sybase_orm`.

## Conexión Única (Simple)

Para proyectos que conectan a una sola base de datos Sybase ASE:

### Opción 1: URL de conexión (recomendada)

```yaml
sybase_orm:
    connection:
        url: '%env(DATABASE_URL)%'
```

Formato de la URL:
```
sybase://usuario:password@host:puerto/base_de_datos?charset=UTF-8&persistent=true
```

Ejemplo en `.env`:
```dotenv
DATABASE_URL="sybase://sa:mi_password@192.168.1.100:5000/produccion?charset=UTF-8"
```

### Opción 2: Parámetros individuales

```yaml
sybase_orm:
    connection:
        host: '%env(SYBASE_HOST)%'
        port: '%env(int:SYBASE_PORT)%'
        database: '%env(SYBASE_DATABASE)%'
        username: '%env(SYBASE_USERNAME)%'
        password: '%env(SYBASE_PASSWORD)%'
        charset: 'UTF-8'
        persistent: false
        charset_conversion: false
        read_only: false
```

> **Importante:** Cuando se proporciona `url`, los parámetros individuales (`host`, `port`, etc.) se ignoran.

## Múltiples Conexiones

Para proyectos que necesitan conectarse a múltiples bases de datos Sybase:

```yaml
sybase_orm:
    connections:
        default:
            url: '%env(DATABASE_URL)%'

        reporting:
            host: '%env(REPORTING_HOST)%'
            port: 5000
            database: 'reports_db'
            username: '%env(REPORTING_USER)%'
            password: '%env(REPORTING_PASS)%'
            read_only: true

        legacy:
            url: '%env(LEGACY_DATABASE_URL)%'
            charset_conversion: true
```

Con múltiples conexiones, la primera definida (`default` en el ejemplo) se registra como la conexión principal y es la que se inyecta cuando usas las interfaces directamente.

Para acceder a conexiones específicas, usa `EntityManagerRegistry`:

```php
use SybaseORM\ORM\EntityManagerRegistry;

class ReportService
{
    public function __construct(
        private readonly EntityManagerRegistry $registry,
    ) {}

    public function getReportData(): array
    {
        $em = $this->registry->getManager('reporting');
        return $em->findAll(ReportEntry::class);
    }
}
```

## Parámetros de Conexión

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `url` | string | `null` | URL completa de conexión (override de parámetros individuales) |
| `host` | string | `localhost` | Host del servidor Sybase ASE |
| `port` | integer | `5000` | Puerto del servidor |
| `database` | string | `null` | Nombre de la base de datos |
| `username` | string | `null` | Usuario de conexión |
| `password` | string | `''` | Contraseña de conexión |
| `charset` | string | `UTF-8` | Juego de caracteres de la conexión |
| `persistent` | boolean | `false` | Usar conexiones persistentes |
| `charset_conversion` | boolean | `false` | Conversión transparente UTF-8 ↔ ISO-8859-1 |
| `read_only` | boolean | `false` | Bloquea INSERT/UPDATE/DELETE y transacciones |

## Validación de Conexión

La configuración requiere **obligatoriamente** una de estas combinaciones:
- `url` proporcionada, **o**
- `database` + `username` proporcionados

Si no se cumple ninguna, se lanza un error de validación:
```
La conexión requiere "url" o los parámetros "database" y "username".
```

## Directorios de Entidades

Define dónde buscar las clases de entidad mapeadas:

```yaml
sybase_orm:
    entity_directories:
        - '%kernel.project_dir%/src/Entity'
        - '%kernel.project_dir%/src/Domain/Model'
```

Valor por defecto: `['%kernel.project_dir%/src/Entity']`

El bundle escanea estos directorios recursivamente buscando clases PHP con el atributo `#[Entity]`.

## Directorio de Proxies

Los proxies son clases generadas que permiten lazy loading de entidades:

```yaml
sybase_orm:
    proxy_directory: '%kernel.cache_dir%/sybase_orm/proxies'
```

Valor por defecto: `'%kernel.cache_dir%/sybase_orm/proxies'`

> **Nota:** En producción, genera los proxies durante el deploy con `php bin/console sybase:proxy:generate`.

## Directorio de Migraciones

Define dónde se almacenan los archivos de migración:

```yaml
sybase_orm:
    migrations_directory: '%kernel.project_dir%/sybase_ase/migrations'
```

Valor por defecto: `'%kernel.project_dir%/sybase_ase/migrations'`

## Permisos de Archivos y Directorios

Controla los permisos con los que se crean los archivos generados (proxies, caché de metadatos) y sus directorios:

```yaml
sybase_orm:
    file_permissions: 0o666
    directory_permissions: 0o777
```

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `file_permissions` | integer (octal) | `0o666` | Permisos para archivos generados (proxies, metadata cache) |
| `directory_permissions` | integer (octal) | `0o777` | Permisos para directorios creados (proxy dir, cache dir) |

Estos valores se pasan a `MetadataReader` y `ProxyGenerator` para que los archivos generados tengan los permisos correctos en el sistema de archivos.

## Configuración de Caché

El bundle soporta caché de segundo nivel con Redis y un patrón de circuit breaker para tolerancia a fallos:

```yaml
sybase_orm:
    cache:
        enabled: true
        adapter: redis                  # Tipo de adaptador: 'redis' o null
        default_ttl: 3600              # TTL por defecto en segundos
        prefix: 'sybase_orm:'          # Prefijo de claves en Redis
        failure_threshold: 3            # Fallos consecutivos antes de abrir el circuit breaker
        cooldown_seconds: 60            # Segundos de espera antes de reintentar tras apertura del circuit breaker
```

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `enabled` | boolean | `false` | Habilita/deshabilita la caché de segundo nivel |
| `adapter` | string | `null` | Tipo de adaptador de caché (`redis` o `null`) |
| `dsn` | string | `null` | DSN para adaptador (deprecado, usar nodo `redis`) |
| `default_ttl` | integer | `3600` | TTL por defecto en segundos |
| `prefix` | string | `'sybase_orm:'` | Prefijo para las claves en Redis |
| `failure_threshold` | integer | `3` | Número de fallos consecutivos antes de deshabilitar la caché (circuit breaker) |
| `cooldown_seconds` | integer | `60` | Segundos de espera antes de reintentar la caché después de que el circuit breaker se activa |

### Circuit Breaker

El circuit breaker protege la aplicación cuando Redis no está disponible:

1. Si la conexión a Redis falla `failure_threshold` veces consecutivas, el circuit breaker se abre
2. Mientras está abierto, las operaciones de caché se omiten sin intentar conexión a Redis
3. Después de `cooldown_seconds`, se intenta reconectar automáticamente
4. Si la reconexión es exitosa, el circuit breaker se cierra y la caché vuelve a funcionar

## Configuración de Redis

Cuando `cache.adapter` es `redis`, configura la conexión Redis con el nodo `redis`:

```yaml
sybase_orm:
    redis:
        host: '127.0.0.1'
        port: 6379
        password: null
        database: 0
        timeout: 2.0
        dsn: null
```

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `host` | string | `'127.0.0.1'` | Host del servidor Redis |
| `port` | integer | `6379` | Puerto del servidor Redis |
| `password` | string | `null` | Contraseña de autenticación Redis |
| `database` | integer | `0` | Índice de base de datos Redis (0-15) |
| `timeout` | float | `2.0` | Timeout de conexión en segundos |
| `dsn` | string | `null` | DSN completo (override de host/port). Formato: `redis://[:password@]host:port/database` |

Si se proporciona `dsn`, los valores de `host`, `port`, `password` y `database` se extraen del DSN.

## Variables de Entorno

Tabla resumen de variables de entorno usadas:

| Variable | Ejemplo | Descripción |
|----------|---------|-------------|
| `DATABASE_URL` | `sybase://sa:pass@host:5000/db` | URL de conexión principal |
| `SYBASE_HOST` | `192.168.1.100` | Host (si no usas URL) |
| `SYBASE_PORT` | `5000` | Puerto (si no usas URL) |
| `SYBASE_DATABASE` | `mi_app` | Base de datos (si no usas URL) |
| `SYBASE_USERNAME` | `sa` | Usuario (si no usas URL) |
| `SYBASE_PASSWORD` | `secret` | Contraseña (si no usas URL) |
| `REDIS_HOST` | `127.0.0.1` | Host de Redis para caché |
| `REDIS_PORT` | `6379` | Puerto de Redis |
| `REDIS_PASSWORD` | `secret` | Contraseña de Redis |

## Ejemplo Completo de Producción

```yaml
# config/packages/sybase_orm.yaml
sybase_orm:
    connection:
        url: '%env(DATABASE_URL)%'

    entity_directories:
        - '%kernel.project_dir%/src/Entity'

    proxy_directory: '%kernel.cache_dir%/sybase_orm/proxies'
    migrations_directory: '%kernel.project_dir%/sybase_ase/migrations'

    file_permissions: 0o666
    directory_permissions: 0o777

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
        database: 0
        timeout: 2.0
```

---

[← Anterior: Instalación](01-instalacion.md) | [Índice](../README.md) | [Siguiente: Uso Básico →](03-uso-basico.md)
