# Manual de Usuario — Migraciones

[← Anterior: Repositorios](04-repositorios.md) | [Índice](../README.md) | [Siguiente: Comandos →](06-comandos.md)

---

## Concepto

Las migraciones permiten versionar los cambios de esquema de la base de datos. El bundle compara los metadatos de las entidades con el esquema actual de la base de datos y genera archivos de migración con las sentencias SQL necesarias.

## Directorio de Migraciones

Por defecto, las migraciones se almacenan en:

```
sybase_ase/migrations/
```

Puedes cambiar esta ruta en la configuración:

```yaml
sybase_orm:
    migrations_directory: '%kernel.project_dir%/sybase_ase/migrations'
```

## Flujo de Trabajo

### 1. Modificar entidades

Primero, modifica tus clases de entidad con los cambios deseados:

```php
#[Entity(table: 'products')]
class Product
{
    // ... campos existentes ...

    // Nuevo campo añadido
    #[Column(name: 'sku', type: 'string')]
    private string $sku;
}
```

### 2. Generar la migración

```bash
php bin/console sybase:migrations:generate
```

Salida esperada:
```
Sybase ORM - Generate Migration
================================

Found 5 entity class(es).

 [OK] Migration generated: /var/www/app/sybase_ase/migrations/Version20240115120000.php
```

El comando:
- Escanea los directorios de entidades configurados
- Compara los metadatos de las entidades con el esquema actual en la base de datos
- Genera un archivo de migración si detecta diferencias
- Si no hay cambios, informa que no se generó ninguna migración

### 3. Revisar la migración

Revisa el archivo generado antes de ejecutarlo para asegurar que los cambios son correctos.

### 4. Ejecutar la migración

```bash
php bin/console sybase:migrations:migrate
```

Salida esperada:
```
Sybase ORM - Execute Migrations
=================================

 [OK] Executed 1 migration(s):

 * Version20240115120000.php
```

## Migraciones en Diferentes Entornos

### Desarrollo

En desarrollo, puedes generar y ejecutar migraciones libremente:

```bash
php bin/console sybase:migrations:generate
php bin/console sybase:migrations:migrate
```

### Producción

En producción, solo ejecuta migraciones ya generadas y revisadas:

```bash
# Parte del proceso de deploy
php bin/console sybase:migrations:migrate --env=prod
```

> **Importante:** Nunca ejecutes `sybase:migrations:generate` en producción. Genera las migraciones en desarrollo y guárdalas en el control de versiones.

## Control de Versiones

Incluye el directorio de migraciones en tu repositorio Git:

```
# .gitignore - NO ignorar migraciones
# sybase_ase/migrations/  ← NO añadir esta línea
```

Asegúrate de que el archivo `.gitkeep` existe para que Git trackee el directorio vacío:

```bash
touch sybase_ase/migrations/.gitkeep
```

## Manejo de Errores

Si una migración falla durante la ejecución:

```
Sybase ORM - Execute Migrations
=================================

 [ERROR] Migration failed: Column 'sku' already exists in table 'products'
```

En este caso:
1. Verifica el estado del esquema manualmente
2. Corrige o elimina la migración problemática
3. Vuelve a intentar

## Validación del Esquema

Después de ejecutar migraciones, valida que el esquema coincide con las entidades:

```bash
php bin/console sybase:schema:validate
```

Esto verifica que:
- Todas las tablas mapeadas existen en la base de datos
- Todas las columnas mapeadas existen en sus tablas correspondientes
- Los metadatos de las entidades son válidos

---

[← Anterior: Repositorios](04-repositorios.md) | [Índice](../README.md) | [Siguiente: Comandos →](06-comandos.md)
