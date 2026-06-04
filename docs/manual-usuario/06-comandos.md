# Manual de Usuario — Comandos de Consola

[← Anterior: Migraciones](05-migraciones.md) | [Índice](../README.md) | [Siguiente: Caché →](07-cache.md)

---

## Resumen de Comandos

| Comando | Descripción |
|---------|-------------|
| `sybase:install` | Instala y configura el bundle en el proyecto |
| `sybase:cache:clear` | Limpia la caché del ORM |
| `sybase:migrations:generate` | Genera una migración desde los cambios en entidades |
| `sybase:migrations:migrate` | Ejecuta las migraciones pendientes |
| `sybase:proxy:generate` | Genera las clases proxy para lazy loading |
| `sybase:schema:validate` | Valida el mapeo de entidades contra el esquema de la BD |

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

## sybase:cache:clear

Limpia todas las cachés del ORM: identity map, caché de segundo nivel y caché de metadatos en memoria.

### Sintaxis

```bash
php bin/console sybase:cache:clear
```

### Acciones que realiza

1. Limpia el identity map (entidades en memoria)
2. Limpia la caché de segundo nivel (si está habilitada)
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

## sybase:migrations:generate

Genera un nuevo archivo de migración comparando los metadatos de las entidades con el esquema actual de la base de datos.

### Sintaxis

```bash
php bin/console sybase:migrations:generate
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

## sybase:migrations:migrate

Ejecuta todas las migraciones pendientes en la base de datos.

### Sintaxis

```bash
php bin/console sybase:migrations:migrate
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

### Ejemplo de salida (error)

```
Sybase ORM - Execute Migrations
=================================

 [ERROR] Migration failed: Table 'orders' already exists
```

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

[← Anterior: Migraciones](05-migraciones.md) | [Índice](../README.md) | [Siguiente: Caché →](07-cache.md)
