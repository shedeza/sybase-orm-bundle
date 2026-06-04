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

## Configuración de Caché

```yaml
sybase_orm:
    cache:
        enabled: true
        adapter: 'cache.app'           # Service ID del adaptador de cache
        dsn: 'redis://localhost:6379'  # DSN para adaptadores standalone
        default_ttl: 3600             # TTL por defecto en segundos
```

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `enabled` | boolean | `false` | Habilita/deshabilita la caché |
| `adapter` | string | `null` | ID del servicio del adaptador de caché Symfony |
| `dsn` | string | `null` | DSN para crear adaptador standalone |
| `default_ttl` | integer | `3600` | TTL por defecto en segundos |

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
| `REDIS_URL` | `redis://localhost:6379` | Redis para caché (opcional) |

## Ejemplo Completo de Producción

```yaml
# config/packages/sybase_orm.yaml
sybase_orm:
    connection:
        url: '%env(DATABASE_URL)%'
        charset_conversion: false

    entity_directories:
        - '%kernel.project_dir%/src/Entity'

    proxy_directory: '%kernel.cache_dir%/sybase_orm/proxies'
    migrations_directory: '%kernel.project_dir%/sybase_ase/migrations'

    cache:
        enabled: true
        adapter: 'cache.app'
        default_ttl: 7200
```

---

[← Anterior: Instalación](01-instalacion.md) | [Índice](../README.md) | [Siguiente: Uso Básico →](03-uso-basico.md)
