# Manual de Usuario — Instalación

[← Índice](../README.md) | [Siguiente: Configuración →](02-configuracion.md)

---

## Requisitos Previos

Antes de instalar el bundle, asegúrate de cumplir con los siguientes requisitos:

| Requisito | Versión Mínima | Notas |
|-----------|---------------|-------|
| PHP | 8.1 | Con extensiones `pdo` y `pdo_dblib` |
| Symfony | 6.0 o 7.0 | Framework Bundle requerido |
| shedeza/sybase-orm | ^3.6 | Se instala automáticamente como dependencia |
| Sybase ASE | 15.x+ | Servidor de base de datos |
| FreeTDS | 0.91+ | Driver de conexión a Sybase |

### Verificar extensiones PHP

```bash
php -m | grep pdo_dblib
```

Si no aparece `pdo_dblib`, deberás instalar la extensión. Ver el [Manual de Operación — Requisitos de Infraestructura](../manual-operacion/01-requisitos-infraestructura.md) para instrucciones detalladas.

## Instalación via Composer

```bash
composer require shedeza/sybase-orm-bundle
```

Esto instalará tanto el bundle como la librería `shedeza/sybase-orm` (si no está ya presente).

## Registro del Bundle

### Con Symfony Flex (automático)

Si tu proyecto usa **Symfony Flex**, el bundle se registra automáticamente gracias al archivo `manifest.json` incluido en el paquete. Además, se crean automáticamente:

- `config/packages/sybase_orm.yaml` — archivo de configuración base
- Variable `DATABASE_URL` en `.env`

No se requiere ninguna acción manual.

### Sin Symfony Flex (manual)

Si tu proyecto no usa Flex, sigue estos pasos:

#### 1. Registrar el bundle

Añade el bundle a `config/bundles.php`:

```php
<?php

return [
    // ... otros bundles
    SybaseORM\Bundle\SybaseORMBundle::class => ['all' => true],
];
```

#### 2. Ejecutar el comando de instalación

```bash
php bin/console sybase:install
```

Este comando creará automáticamente:

- `config/packages/sybase_orm.yaml` con la configuración por defecto
- La variable `DATABASE_URL` en tu archivo `.env` (si no existe)
- El directorio `sybase_ase/migrations/` para los archivos de migración

#### 3. Alternativa: instalación manual completa

Si prefieres configurar todo manualmente sin usar el comando `sybase:install`:

1. Crea el archivo de configuración:

```yaml
# config/packages/sybase_orm.yaml
sybase_orm:
    connection:
        url: '%env(DATABASE_URL)%'
    entity_directories:
        - '%kernel.project_dir%/src/Entity'
    proxy_directory: '%kernel.cache_dir%/sybase_orm/proxies'
    migrations_directory: '%kernel.project_dir%/sybase_ase/migrations'
    cache:
        enabled: false
```

2. Añade la variable de entorno a `.env`:

```dotenv
###> sybase-orm/sybase-ase-orm-bundle ###
DATABASE_URL="sybase://sa:!ChangeMe!@127.0.0.1:5000/app?charset=UTF-8"
###< sybase-orm/sybase-ase-orm-bundle ###
```

3. Crea el directorio de migraciones:

```bash
mkdir -p sybase_ase/migrations
```

## Verificación de la Instalación

Para verificar que el bundle se instaló correctamente:

```bash
# Verificar que el bundle está registrado
php bin/console debug:container --tag=console.command | grep sybase

# Debería mostrar los 13 comandos del bundle:
# sybase:install
# sybase:make:entity
# sybase:orm:info
# sybase:migrate
# sybase:migrate:status
# sybase:migrate:generate
# sybase:migrate:rollback
# sybase:migrate:reset
# sybase:migrate:fresh
# sybase:migrate:preview
# sybase:schema:validate
# sybase:cache:clear
# sybase:proxy:generate
```

## Opciones del Comando sybase:install

```bash
php bin/console sybase:install [--force|-f]
```

| Opción | Descripción |
|--------|-------------|
| `--force`, `-f` | Sobreescribe archivos de configuración existentes |

El comando es **idempotente**: si los archivos ya existen, informa del estado sin sobreescribirlos (a menos que se use `--force`).

## Solución de Problemas de Instalación

| Problema | Solución |
|----------|----------|
| `Class "SybaseORM\Bundle\SybaseORMBundle" not found` | Ejecuta `composer dump-autoload` |
| `There are no commands defined in the "sybase" namespace` | Verifica que el bundle está registrado en `bundles.php` |
| `PDOException: could not find driver` | Instala la extensión `pdo_dblib` (ver Manual de Operación) |

---

[← Índice](../README.md) | [Siguiente: Configuración →](02-configuracion.md)
