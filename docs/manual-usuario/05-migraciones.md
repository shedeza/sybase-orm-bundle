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

## Comandos de Migración

El bundle proporciona un conjunto completo de comandos para gestionar migraciones:

| Comando | Descripción |
|---------|-------------|
| `sybase:migrate` | Ejecuta las migraciones pendientes |
| `sybase:migrate:status` | Muestra el estado de cada migración (aplicada/pendiente) |
| `sybase:migrate:generate` | Genera una migración desde los cambios en entidades |
| `sybase:migrate:rollback` | Revierte el último lote de migraciones |
| `sybase:migrate:reset` | Revierte todas las migraciones |
| `sybase:migrate:fresh` | Elimina todas las tablas y re-ejecuta todas las migraciones |
| `sybase:migrate:preview` | Muestra el SQL sin ejecutar |

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
php bin/console sybase:migrate:generate
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

### 3. Previsualizar el SQL

Antes de ejecutar, puedes ver qué SQL se aplicará:

```bash
php bin/console sybase:migrate:preview
```

### 4. Revisar la migración

Revisa el archivo generado antes de ejecutarlo para asegurar que los cambios son correctos.

### 5. Ejecutar la migración

```bash
php bin/console sybase:migrate
```

Salida esperada:
```
Sybase ORM - Execute Migrations
=================================

 [OK] Executed 1 migration(s):

 * Version20240115120000.php
```

### 6. Verificar el estado

```bash
php bin/console sybase:migrate:status
```

## Revertir Migraciones

### Revertir el último lote

```bash
php bin/console sybase:migrate:rollback
```

### Revertir todas las migraciones

```bash
php bin/console sybase:migrate:reset
```

### Reiniciar desde cero (solo desarrollo)

```bash
php bin/console sybase:migrate:fresh
```

> **Precaución:** `sybase:migrate:fresh` elimina todas las tablas y re-ejecuta todas las migraciones. No debe usarse en producción.

## Migraciones en Diferentes Entornos

### Desarrollo

En desarrollo, puedes generar y ejecutar migraciones libremente:

```bash
php bin/console sybase:migrate:generate
php bin/console sybase:migrate
```

### Producción

En producción, solo ejecuta migraciones ya generadas y revisadas:

```bash
# Previsualizar antes de aplicar
php bin/console sybase:migrate:preview --env=prod

# Ejecutar
php bin/console sybase:migrate --env=prod
```

> **Importante:** Nunca ejecutes `sybase:migrate:generate` en producción. Genera las migraciones en desarrollo y guárdalas en el control de versiones.

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
2. Usa `sybase:migrate:status` para ver qué migraciones se aplicaron
3. Corrige o elimina la migración problemática
4. Vuelve a intentar

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
