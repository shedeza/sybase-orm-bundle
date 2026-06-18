# Manual de Usuario — Comandos de Consola

[← Anterior: Migraciones](05-migraciones.md) | [Índice](../README.md) | [Siguiente: Caché →](07-cache.md)

---

## Resumen de Comandos

El bundle proporciona 13 comandos de consola:

| Comando | Descripción |
|---------|-------------|
| `sybase:install` | Instala y configura el bundle en el proyecto |
| `sybase:make:entity` | Genera una nueva clase de entidad con atributos de mapeo |
| `sybase:orm:info` | Muestra información sobre las entidades mapeadas |
| `sybase:migrate` | Ejecuta las migraciones pendientes |
| `sybase:migrate:status` | Muestra el estado actual de las migraciones |
| `sybase:migrate:generate` | Genera una migración desde los cambios en entidades |
| `sybase:migrate:rollback` | Revierte el último lote de migraciones |
| `sybase:migrate:reset` | Revierte todas las migraciones |
| `sybase:migrate:fresh` | Elimina todas las tablas y re-ejecuta todas las migraciones |
| `sybase:migrate:preview` | Muestra el SQL de migraciones pendientes sin ejecutarlas |
| `sybase:schema:validate` | Valida el mapeo de entidades contra el esquema de la BD |
| `sybase:cache:clear` | Limpia la caché del ORM |
| `sybase:proxy:generate` | Genera las clases proxy para lazy loading |

> Los comandos de migración y esquema son adaptaciones de los comandos nativos de `shedeza/sybase-orm` ^3.6 a la consola de Symfony mediante `OrmCommandAdapter`.

---

## sybase:install

Instala y configura SybaseORM en el proyecto Symfony actual.

### Sintaxis

```bash
php bin/console sybase:install [--force|-f]
```

### Opciones

| Opción | Descripción |
|--------|-------------|
| `--force`, `-f` | Sobreescribe archivos de configuración existentes |

### Acciones que realiza

1. Crea `config/packages/sybase_orm.yaml` con la configuración por defecto
2. Agrega `DATABASE_URL` al archivo `.env` (si no existe)
3. Crea el directorio `sybase_ase/migrations/`
4. Verifica y registra el bundle en `config/bundles.php`

### Ejemplo de salida

```
SybaseORM - Instalación
========================

  ✓ Creado config/packages/sybase_orm.yaml
  ✓ Agregado DATABASE_URL a .env
  ✓ Creado directorio sybase_ase/migrations/
  ✓ Bundle registrado en config/bundles.php

 [OK] SybaseORM instalado correctamente. Edita DATABASE_URL en .env con tus datos de conexión.
```

---

## sybase:make:entity

Genera una nueva clase de entidad con los atributos de mapeo `#[Entity]`, `#[Id]` y `#[Column]`.

### Sintaxis

```bash
php bin/console sybase:make:entity
```

El comando es interactivo: solicita el nombre de la entidad, la tabla, y las columnas.

### Ejemplo de salida

```
Sybase ORM - Make Entity
=========================

Entity class name (e.g. Product): Category
Table name [categories]:

 Add fields (leave blank to finish):

 Field name: name
 Field type (string, int, bool, float, decimal, datetime) [string]:
 Column name [name]:

 Field name: slug
 Field type [string]:
 Column name [slug]:

 Field name:

 [OK] Entity created: src/Entity/Category.php
```

---

## sybase:orm:info

Muestra información sobre todas las entidades mapeadas: clase, tabla, número de columnas y repositorio asociado.

### Sintaxis

```bash
php bin/console sybase:orm:info
```

### Ejemplo de salida

```
Sybase ORM - Entity Info
=========================

 Found 5 mapped entity class(es):

 ┌───────────────────────────┬─────────────┬─────────┬─────────────────────────────────────┐
 │ Entity                    │ Table       │ Columns │ Repository                          │
 ├───────────────────────────┼─────────────┼─────────┼─────────────────────────────────────┤
 │ App\Entity\Product        │ products    │ 5       │ App\Repository\ProductRepository    │
 │ App\Entity\Category       │ categories  │ 3       │ App\Repository\CategoryRepository   │
 │ App\Entity\Order          │ orders      │ 8       │ App\Repository\OrderRepository      │
 │ App\Entity\User           │ users       │ 6       │ —                                   │
 │ App\Entity\AuditLog       │ audit_log   │ 4       │ —                                   │
 └───────────────────────────┴─────────────┴─────────┴─────────────────────────────────────┘
```

---

## sybase:migrate

Ejecuta todas las migraciones pendientes en la base de datos.

### Sintaxis

```bash
php bin/console sybase:migrate
```

### Ejemplo de salida (con migraciones pendientes)

```
Sybase ORM - Execute Migrations
=================================

 [OK] Executed 3 migration(s):

 * Version20240110090000.php
 * Version20240112140000.php
 * Version20240115143022.php
```

### Ejemplo de salida (sin migraciones pendientes)

```
Sybase ORM - Execute Migrations
=================================

 [OK] No pending migrations to execute.
```

---

## sybase:migrate:status

Muestra el estado actual de las migraciones: cuáles están ejecutadas, cuáles pendientes.

### Sintaxis

```bash
php bin/console sybase:migrate:status
```

### Ejemplo de salida

```
Sybase ORM - Migration Status
===============================

 ┌──────────────────────────────┬──────────┬─────────────────────┐
 │ Migration                    │ Status   │ Executed At          │
 ├──────────────────────────────┼──────────┼─────────────────────┤
 │ Version20240110090000.php    │ Applied  │ 2024-01-10 09:01:23 │
 │ Version20240112140000.php    │ Applied  │ 2024-01-12 14:05:11 │
 │ Version20240115143022.php    │ Pending  │ —                   │
 └──────────────────────────────┴──────────┴─────────────────────┘

 Applied: 2 | Pending: 1
```

---

## sybase:migrate:generate

Genera un nuevo archivo de migración comparando los metadatos de las entidades con el esquema actual de la base de datos.

### Sintaxis

```bash
php bin/console sybase:migrate:generate
```

### Requisitos

- Conexión activa a la base de datos
- Al menos una entidad en los directorios configurados

### Ejemplo de salida (con cambios)

```
Sybase ORM - Generate Migration
================================

Found 8 entity class(es).

 [OK] Migration generated: /var/www/app/sybase_ase/migrations/Version20240115143022.php
```

### Ejemplo de salida (sin cambios)

```
Sybase ORM - Generate Migration
================================

Found 8 entity class(es).

 [OK] No schema changes detected. No migration generated.
```

---

## sybase:migrate:rollback

Revierte el último lote de migraciones ejecutadas.

### Sintaxis

```bash
php bin/console sybase:migrate:rollback
```

### Ejemplo de salida

```
Sybase ORM - Rollback Migrations
==================================

 [OK] Rolled back 2 migration(s):

 * Version20240115143022.php
 * Version20240112140000.php
```

---

## sybase:migrate:reset

Revierte **todas** las migraciones ejecutadas, dejando la base de datos en su estado inicial.

### Sintaxis

```bash
php bin/console sybase:migrate:reset
```

### Ejemplo de salida

```
Sybase ORM - Reset Migrations
===============================

 [WARNING] This will rollback ALL migrations.

 [OK] Reset complete. Rolled back 5 migration(s).
```

> **Precaución:** Este comando no debe usarse en producción. Es útil en desarrollo para reiniciar el esquema desde cero.

---

## sybase:migrate:fresh

Elimina todas las tablas de la base de datos y re-ejecuta todas las migraciones desde el inicio.

### Sintaxis

```bash
php bin/console sybase:migrate:fresh
```

### Ejemplo de salida

```
Sybase ORM - Fresh Migration
==============================

 [WARNING] This will DROP all tables and re-run all migrations.

Dropped all tables.
Running migrations...

 [OK] Fresh migration complete. Executed 5 migration(s).
```

> **Precaución:** Este comando destruye todos los datos. Solo debe usarse en desarrollo o testing.

---

## sybase:migrate:preview

Muestra el SQL que ejecutarían las migraciones pendientes sin aplicarlas a la base de datos.

### Sintaxis

```bash
php bin/console sybase:migrate:preview
```

### Ejemplo de salida

```
Sybase ORM - Preview Migrations
=================================

 Version20240115143022.php:

    ALTER TABLE products ADD sku VARCHAR(50) NOT NULL
    CREATE INDEX idx_products_sku ON products(sku)

 [OK] 1 pending migration(s) previewed. No changes applied.
```

### Cuándo usarlo

- Antes de ejecutar migraciones en staging o producción para revisar el SQL
- En CI/CD como paso de verificación
- Para generar scripts SQL que se ejecutarán manualmente por un DBA

---

## sybase:schema:validate

Valida que el mapeo de entidades es correcto y que las tablas/columnas existen en la base de datos.

### Sintaxis

```bash
php bin/console sybase:schema:validate
```

### Requisitos

- Conexión activa a la base de datos
- Al menos una entidad en los directorios configurados

### Validaciones que realiza

1. **Metadatos:** Verifica que cada entidad tiene metadatos válidos
2. **Tablas:** Verifica que la tabla mapeada existe en la base de datos (`sysobjects`)
3. **Columnas:** Verifica que cada columna mapeada existe en la tabla (`syscolumns`)

### Ejemplo de salida (éxito)

```
Sybase ORM - Schema Validation
================================

  ✓ App\Entity\Product → products (5 columns)
  ✓ App\Entity\Category → categories (3 columns)
  ✓ App\Entity\Order → orders (8 columns)

 [OK] All 3 entities validated successfully.
```

### Ejemplo de salida (con errores)

```
Sybase ORM - Schema Validation
================================

  ✓ App\Entity\Product → products (5 columns)

 [ERROR] [App\Entity\Order] Table "orders" does not exist in database.

 [WARNING] [App\Entity\Category] Column "slug" not found in table "categories".

 [ERROR] Validation failed: 1 error(s), 1 entity(ies) validated.
```

### Cuándo usarlo

- Después de ejecutar migraciones, para confirmar que todo está sincronizado
- En CI/CD como paso de verificación
- Al depurar errores de mapeo

---

## sybase:cache:clear

Limpia todas las cachés del ORM: identity map, caché de segundo nivel y caché de metadatos en memoria.

### Sintaxis

```bash
php bin/console sybase:cache:clear
```

### Acciones que realiza

1. Limpia el identity map (entidades en memoria)
2. Limpia la caché de segundo nivel (Redis, si está habilitada)
3. Limpia la caché de metadatos en memoria estática

### Ejemplo de salida

```
Sybase ORM - Clear Cache
=========================

Cleared identity map and second-level cache.
Cleared metadata memory cache.

 [OK] All SybaseORM caches cleared.
```

### Cuándo usarlo

- Después de cambios en los atributos de mapeo de entidades
- Si experimentas datos desactualizados en desarrollo
- Como parte del proceso de deploy en producción

---

## sybase:proxy:generate

Genera las clases proxy para lazy loading de todas las entidades mapeadas.

### Sintaxis

```bash
php bin/console sybase:proxy:generate
```

### Acciones que realiza

1. Escanea los directorios de entidades configurados
2. Identifica todas las clases con el atributo `#[Entity]`
3. Genera una clase proxy para cada entidad en el directorio de proxies configurado

### Ejemplo de salida

```
Sybase ORM - Generate Proxies
===============================

Generated proxy: App\Entity\Proxy\ProductProxy
Generated proxy: App\Entity\Proxy\CategoryProxy
Generated proxy: App\Entity\Proxy\OrderProxy

 [OK] Generated 3 proxy class(es).
```

### Cuándo usarlo

- **Producción:** Siempre como parte del proceso de deploy
- **Desarrollo:** No es estrictamente necesario (se generan on-demand), pero mejora el rendimiento

---

[← Anterior: Migraciones](05-migraciones.md) | [Índice](../README.md) | [Siguiente: Caché →](07-cache.md)
