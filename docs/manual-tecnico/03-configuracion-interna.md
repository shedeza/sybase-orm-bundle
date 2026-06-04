# Manual Técnico — Configuración Interna

[← Anterior: Contenedor DI](02-contenedor-di.md) | [Índice](../README.md) | [Siguiente: Ciclo de Vida →](04-ciclo-vida-consulta.md)

---

## Configuration Tree Builder

La clase `Configuration` implementa `ConfigurationInterface` y define la estructura completa de opciones permitidas usando el `TreeBuilder` de Symfony.

## Estructura del Árbol

```
sybase_orm (root)
├── connection (array, opcional)
│   ├── url (scalar, default: null)
│   ├── host (scalar, default: 'localhost')
│   ├── port (integer, default: 5000)
│   ├── database (scalar, default: null)
│   ├── username (scalar, default: null)
│   ├── password (scalar, default: '')
│   ├── charset (scalar, default: 'UTF-8')
│   ├── persistent (boolean, default: false)
│   ├── charset_conversion (boolean, default: false)
│   └── read_only (boolean, default: false)
├── connections (array, prototyped, key = nombre)
│   └── {name} (array)
│       ├── url (scalar, default: null)
│       ├── host (scalar, default: 'localhost')
│       ├── port (integer, default: 5000)
│       ├── database (scalar, default: null)
│       ├── username (scalar, default: null)
│       ├── password (scalar, default: '')
│       ├── charset (scalar, default: 'UTF-8')
│       ├── persistent (boolean, default: false)
│       ├── charset_conversion (boolean, default: false)
│       └── read_only (boolean, default: false)
├── entity_directories (array de scalars, default: ['%kernel.project_dir%/src/Entity'])
├── proxy_directory (scalar, default: '%kernel.cache_dir%/sybase_orm/proxies')
├── migrations_directory (scalar, default: '%kernel.project_dir%/sybase_ase/migrations')
└── cache (array, con defaults)
    ├── enabled (boolean, default: false)
    ├── adapter (scalar, default: null)
    ├── dsn (scalar, default: null)
    └── default_ttl (integer, default: 3600)
```

## Validación Personalizada

El nodo `connection` incluye validación custom que asegura coherencia:

```php
->validate()
    ->ifTrue(function (array $v) {
        return $v['url'] === null
            && ($v['database'] === null || $v['username'] === null);
    })
    ->thenInvalid('La conexión requiere "url" o los parámetros "database" y "username".')
->end()
```

**Regla:** Si no se proporciona `url`, entonces `database` Y `username` son obligatorios.

## Nodo Prototyped (connections)

El nodo `connections` usa `useAttributeAsKey('name')` y `arrayPrototype()`, lo que permite definir un número arbitrario de conexiones nombradas:

```php
->arrayNode('connections')
    ->useAttributeAsKey('name')
    ->arrayPrototype()
        ->children()
            // ... mismos nodos que 'connection'
        ->end()
    ->end()
->end()
```

## Valores por Defecto

### entity_directories
```php
->arrayNode('entity_directories')
    ->scalarPrototype()->end()
    ->defaultValue(['%kernel.project_dir%/src/Entity'])
->end()
```

### cache (con defaults automáticos)
```php
->arrayNode('cache')
    ->addDefaultsIfNotSet()
    ->children()
        ->booleanNode('enabled')->defaultFalse()->end()
        // ...
    ->end()
->end()
```

`addDefaultsIfNotSet()` asegura que el nodo `cache` siempre existe en la configuración procesada, incluso si el usuario no lo define.

## Procesamiento en el Extension

```php
$configuration = new Configuration();
$config = $this->processConfiguration($configuration, $configs);
```

El método `processConfiguration()`:
1. Merge todas las configuraciones de diferentes archivos (packages, env-specific)
2. Aplica valores por defecto
3. Valida contra el árbol definido
4. Retorna un array normalizado

## Cómo Extender la Configuración

Si necesitas añadir nuevos nodos al árbol de configuración (para una versión futura del bundle):

1. Añade el nodo en `Configuration::getConfigTreeBuilder()`
2. Procesa el nuevo valor en `SybaseORMExtension::load()`
3. Registra el servicio/parámetro correspondiente

Ejemplo de añadir una opción:

```php
// En Configuration
->booleanNode('debug_queries')
    ->defaultFalse()
    ->info('Habilita logging detallado de queries')
->end()

// En SybaseORMExtension
$container->setParameter('sybase_orm.debug_queries', $config['debug_queries']);
```

## Alias del Extension

```php
public function getAlias(): string
{
    return 'sybase_orm';
}
```

Este alias determina la clave raíz en YAML (`sybase_orm:`) y el prefijo de parámetros del contenedor.

---

[← Anterior: Contenedor DI](02-contenedor-di.md) | [Índice](../README.md) | [Siguiente: Ciclo de Vida →](04-ciclo-vida-consulta.md)
