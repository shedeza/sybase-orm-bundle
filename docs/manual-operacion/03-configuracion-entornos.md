# Manual de Operación — Configuración de Entornos

[← Anterior: Despliegue](02-despliegue.md) | [Índice](../README.md) | [Siguiente: Monitorización →](04-monitorizacion.md)

---

## Entornos Symfony

Symfony maneja tres entornos principales. La configuración del bundle se adapta a cada uno:

| Entorno | `APP_ENV` | Profiler | Caché ORM | Proxies |
|---------|-----------|----------|-----------|---------|
| Desarrollo | `dev` | ✅ Activo | ❌ Deshabilitada | On-demand |
| Staging | `staging`/`test` | Opcional | ✅ Habilitada | Pre-generados |
| Producción | `prod` | ❌ Inactivo | ✅ Habilitada | Pre-generados |

## Configuración por Entorno

### Desarrollo (dev)

```yaml
# config/packages/dev/sybase_orm.yaml
sybase_orm:
    connection:
        url: '%env(DATABASE_URL)%'
    cache:
        enabled: false
```

Variables en `.env.local`:
```dotenv
DATABASE_URL="sybase://sa:dev_password@localhost:5000/app_dev?charset=UTF-8"
```

### Staging

```yaml
# config/packages/staging/sybase_orm.yaml
sybase_orm:
    connection:
        url: '%env(DATABASE_URL)%'
    cache:
        enabled: true
        adapter: 'cache.app'
        default_ttl: 1800
```

### Producción (prod)

```yaml
# config/packages/prod/sybase_orm.yaml
sybase_orm:
    connection:
        url: '%env(DATABASE_URL)%'
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

## Variables de Entorno

### Tabla completa

| Variable | Requerida | Default | Descripción |
|----------|-----------|---------|-------------|
| `DATABASE_URL` | ✅ Sí | — | URL de conexión principal a Sybase ASE |
| `REDIS_HOST` | ❌ Opcional | `127.0.0.1` | Host de Redis para caché |
| `REDIS_PORT` | ❌ Opcional | `6379` | Puerto de Redis |
| `REDIS_PASSWORD` | ❌ Opcional | — | Contraseña de Redis |
| `APP_ENV` | ✅ Sí | `dev` | Entorno de Symfony |
| `APP_SECRET` | ✅ Sí | — | Secret de Symfony (no específico del bundle) |

### Variables opcionales (sin URL)

| Variable | Ejemplo | Descripción |
|----------|---------|-------------|
| `SYBASE_HOST` | `192.168.1.100` | Host del servidor Sybase |
| `SYBASE_PORT` | `5000` | Puerto del servidor |
| `SYBASE_DATABASE` | `production` | Nombre de la base de datos |
| `SYBASE_USERNAME` | `app_user` | Usuario de conexión |
| `SYBASE_PASSWORD` | `SecurePass123` | Contraseña |

### Formato de DATABASE_URL

```
sybase://[usuario]:[password]@[host]:[puerto]/[database]?charset=[charset]&persistent=[true|false]
```

Ejemplos:
```dotenv
# Básico
DATABASE_URL="sybase://sa:password@dbserver:5000/myapp"

# Con charset y persistente
DATABASE_URL="sybase://app:pass@db.prod.internal:5000/production?charset=UTF-8&persistent=true"
```

## Gestión de Secrets

### Symfony Secrets (producción)

Para no almacenar contraseñas en texto plano, usa Symfony Secrets:

```bash
# Generar claves (solo una vez)
php bin/console secrets:generate-keys --env=prod

# Almacenar el secret
php bin/console secrets:set DATABASE_URL --env=prod
# Introduce: sybase://app:RealPassword@prod-db:5000/production
```

En `config/packages/prod/sybase_orm.yaml`:
```yaml
sybase_orm:
    connection:
        url: '%env(DATABASE_URL)%'  # Se resuelve desde Symfony Secrets
```

### Variables en plataformas cloud

**AWS (Parameter Store / Secrets Manager):**
```bash
# Definir en el task definition de ECS
DATABASE_URL: "arn:aws:secretsmanager:us-east-1:123456:secret:prod/database-url"
```

**Docker Secrets:**
```yaml
services:
  app:
    secrets:
      - db_url
    environment:
      DATABASE_URL_FILE: /run/secrets/db_url

secrets:
  db_url:
    external: true
```

## Archivos .env

Estructura recomendada de archivos `.env`:

```
.env                    # Valores por defecto (versionado)
.env.local              # Override local (NO versionado)
.env.prod               # Defaults de producción (versionado)
.env.prod.local         # Override de producción (NO versionado)
.env.test               # Defaults de testing (versionado)
```

### .env (base, versionado)

```dotenv
###> sybase-orm/sybase-ase-orm-bundle ###
DATABASE_URL="sybase://sa:!ChangeMe!@127.0.0.1:5000/app?charset=UTF-8"
###< sybase-orm/sybase-ase-orm-bundle ###
```

### .env.local (desarrollo, NO versionado)

```dotenv
DATABASE_URL="sybase://sa:mi_password_local@localhost:5000/dev_db?charset=UTF-8"
```

### .env.prod (producción, versionado)

```dotenv
# Solo estructura, valores reales en secrets o .env.prod.local
APP_ENV=prod
APP_DEBUG=0
```

## Conexión Read-Only para Réplicas

En producción con réplicas de lectura:

```yaml
sybase_orm:
    connections:
        default:
            url: '%env(DATABASE_URL)%'

        read_replica:
            url: '%env(DATABASE_READ_URL)%'
            read_only: true
```

```dotenv
DATABASE_URL="sybase://app:pass@primary-db:5000/production"
DATABASE_READ_URL="sybase://reader:pass@replica-db:5000/production"
```

## Verificar Configuración

```bash
# Ver configuración actual del bundle
php bin/console debug:config sybase_orm

# Ver configuración resuelta (con env vars)
php bin/console debug:config sybase_orm --env=prod
```

---

[← Anterior: Despliegue](02-despliegue.md) | [Índice](../README.md) | [Siguiente: Monitorización →](04-monitorizacion.md)
