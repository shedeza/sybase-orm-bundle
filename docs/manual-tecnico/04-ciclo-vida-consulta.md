# Manual Técnico — Ciclo de Vida de una Consulta

[← Anterior: Configuración Interna](03-configuracion-interna.md) | [Índice](../README.md) | [Siguiente: Profiler →](05-profiler.md)

---

## Flujo de una Operación de Lectura (find)

```mermaid
sequenceDiagram
    participant App as Application Code
    participant EM as EntityManager
    participant IM as IdentityMap
    participant Cache as CacheManager
    participant Hyd as Hydrator
    participant Dial as SybaseDialect
    participant UoW as UnitOfWork
    participant CM as ConnectionManager
    participant DB as Sybase ASE
    participant DC as DataCollector

    App->>EM: find(Product::class, 42)
    EM->>IM: get(Product::class, 42)
    alt Entidad en IdentityMap
        IM-->>EM: Product instance
        EM-->>App: Product
    else No está en cache
        EM->>Cache: get(key)
        alt En caché de segundo nivel
            Cache-->>EM: cached data
            EM->>Hyd: hydrate(data)
            Hyd-->>EM: Product instance
            EM->>IM: put(Product::class, 42, instance)
            EM-->>App: Product
        else No en caché
            EM->>Dial: buildSelectQuery(metadata, criteria)
            Dial-->>EM: SQL string
            EM->>CM: executeQuery(sql, params)
            CM->>DC: addQuery(sql, params, time)
            CM->>DB: PDO::prepare + execute
            DB-->>CM: PDOStatement
            CM-->>EM: result rows
            EM->>Hyd: hydrate(row, metadata)
            Hyd->>Hyd: TypeCaster::cast(columns)
            Hyd-->>EM: Product instance
            EM->>IM: put(Product::class, 42, instance)
            EM->>Cache: set(key, data)
            EM-->>App: Product
        end
    end
```

## Flujo de una Operación de Escritura (persist + flush)

```mermaid
sequenceDiagram
    participant App as Application Code
    participant EM as EntityManager
    participant UoW as UnitOfWork
    participant Hooks as HookDispatcher
    participant Dial as SybaseDialect
    participant TC as TypeCaster
    participant CM as ConnectionManager
    participant DB as Sybase ASE
    participant IM as IdentityMap

    App->>EM: persist(product)
    EM->>UoW: scheduleInsert(product)
    UoW->>UoW: track entity state

    App->>EM: flush()
    EM->>UoW: commit()
    UoW->>Hooks: dispatch(PreFlush)
    UoW->>CM: beginTransaction()

    loop Para cada INSERT programado
        UoW->>Hooks: dispatch(PreInsert, entity)
        UoW->>TC: castToDatabase(entity properties)
        TC-->>UoW: typed values
        UoW->>Dial: buildInsertQuery(metadata, values)
        Dial-->>UoW: INSERT SQL
        UoW->>CM: executeQuery(sql, params)
        CM->>DB: execute INSERT
        DB-->>CM: affected rows / identity
        UoW->>Hooks: dispatch(PostInsert, entity)
        UoW->>IM: register(entity)
    end

    loop Para cada UPDATE detectado
        UoW->>Hooks: dispatch(PreUpdate, entity)
        UoW->>UoW: computeChangeSet(entity)
        UoW->>Dial: buildUpdateQuery(metadata, changes)
        UoW->>CM: executeQuery(sql, params)
        UoW->>Hooks: dispatch(PostUpdate, entity)
    end

    loop Para cada DELETE programado
        UoW->>Hooks: dispatch(PreDelete, entity)
        UoW->>Dial: buildDeleteQuery(metadata, id)
        UoW->>CM: executeQuery(sql, params)
        UoW->>IM: remove(entity)
        UoW->>Hooks: dispatch(PostDelete, entity)
    end

    UoW->>CM: commit()
    UoW->>Hooks: dispatch(PostFlush)
    UoW-->>EM: success
    EM-->>App: void
```

## Componentes Clave en el Flujo

### ConnectionManager

Gestiona la conexión PDO subyacente:
- Lazy connection (se conecta en la primera query)
- Soporte de conexiones persistentes
- Transacciones (begin, commit, rollback)
- Conversión de charset si está habilitada

### SybaseDialect

Genera SQL específico para Sybase ASE:
- Sintaxis `TOP n` en lugar de `LIMIT`
- Funciones de fecha/hora específicas de ASE
- Escape de identificadores con corchetes `[nombre]`
- Tipos de dato Sybase (varchar, datetime, numeric, etc.)

### TypeCaster

Convierte entre tipos PHP y tipos de base de datos:
- `int` ↔ `INTEGER`
- `float` ↔ `NUMERIC/DECIMAL`
- `string` ↔ `VARCHAR/CHAR`
- `bool` ↔ `BIT/TINYINT`
- `\DateTimeInterface` ↔ `DATETIME`
- Tipos custom extensibles

### Hydrator

Transforma rows de resultado en objetos PHP:
- Lee metadatos para mapear columnas → propiedades
- Aplica TypeCaster para conversión de tipos
- Gestiona relaciones lazy via ProxyGenerator
- Registra entidades en el IdentityMap
- Resuelve embedded objects (columnas con dot notation)

### HookDispatcher

Despacha eventos en puntos clave del ciclo de vida:
- `PreFlush`, `PostFlush`
- `PreInsert`, `PostInsert`
- `PreUpdate`, `PostUpdate`
- `PreDelete`, `PostDelete`
- Permite lógica custom (auditoría, validación, etc.)

## Manejo de Errores

Si una operación falla durante `flush()`:

```mermaid
flowchart TD
    A[flush] --> B[beginTransaction]
    B --> C[execute operations]
    C -->|Error| D[rollback]
    C -->|Success| E[commit]
    D --> F[throw exception]
    E --> G[return success]
```

El UnitOfWork ejecuta rollback automático si cualquier operación falla, manteniendo la consistencia de la base de datos.

---

[← Anterior: Configuración Interna](03-configuracion-interna.md) | [Índice](../README.md) | [Siguiente: Profiler →](05-profiler.md)
