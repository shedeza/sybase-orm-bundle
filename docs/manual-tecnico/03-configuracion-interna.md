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
├── file_permissions (integer, default: 0o666)
├── directory_permissions (integer, default: 0o777)
├── cache (array, con defaults)
│   ├── enabled (boolean, default: false)
│   ├── adapter (scalar, default: null)
│   ├── dsn (scalar, default: null) [deprecado]
│   ├── default_ttl (integer, default: 3600)
│   ├── prefix (scalar, default: 'sybase_orm:')
│   ├── failure_threshold (integer, default: 3)
│   └── cooldown_seconds (integer, default: 60)
└── redis (array, con defaults)
    ├── host (scalar, default: '127.0.0.1')
    ├── port (integer, default: 6379)
    ├── password (scalar, default: null)
    ├── database (integer, default: 0)
    ├── timeout (float, default: 2.0)
    └── dsn (scalar, default: null)
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

## Nodos de Permisos

Los permisos de archivos y directorios se aplican a proxies generados y caché de metadatos:

```php
->integerNode('file_permissions')
    ->defaultValue(0o666)
    ->info('File permissions for generated proxies and metadata cache (octal).')
->end()
->integerNode('directory_permissions')
    ->defaultValue(0o777)
    ->info('Directory permissions for proxy and cache directories (octal).')
->end()
```

Estos valores se pasan al `MetadataReader` y `ProxyGenerator`:

```php
$definition = new Definition(MetadataReader::class, [
    $config['proxy_directory'],
    true,
    $config['directory_permissions'] ?? 0o777,
    $config['file_permissions'] ?? 0o666,
]);
```

## Nodo Cache con Circuit Breaker

El nodo `cache` incluye configuración para el patrón circuit breaker:

```php
->arrayNode('cache')
    ->addDefaultsIfNotSet()
    ->children()
        ->booleanNode('enabled')->defaultFalse()->end()
        ->scalarNode('adapter')->defaultNull()->end()
        ->scalarNode('dsn')->defaultNull()->end()
        ->integerNode('default_ttl')->defaultValue(3600)->end()
        ->scalarNode('prefix')->defaultValue('sybase_orm:')->end()
        ->integerNode('failure_threshold')->defaultValue(3)->end()
        ->integerNode('cooldown_seconds')->defaultValue(60)->end()
    ->end()
->end()
```

- `failure_threshold`: Número de fallos consecutivos antes de que el circuit breaker deshabilite la caché
- `cooldown_seconds`: Tiempo de espera antes de reintentar la conexión a Redis

## Nodo Redis

El nodo `redis` centraliza la configuración de conexión a Redis:

```php
->arrayNode('redis')
    ->addDefaultsIfNotSet()
    ->children()
        ->scalarNode('host')->defaultValue('127.0.0.1')->end()
        ->integerNode('port')->defaultValue(6379)->end()
        ->scalarNode('password')->defaultNull()->end()
        ->integerNode('database')->defaultValue(0)->end()
        ->floatNode('timeout')->defaultValue(2.0)->end()
        ->scalarNode('dsn')->defaultNull()->end()
    ->end()
->end()
```

Cuando se proporciona `dsn`, los valores de `host`, `port`, `password` y `database` se extraen automáticamente. El factory method `createRedisConnection()` del Extension maneja esta lógica.

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

`addDefaultsIfNotSet()` asegura que los nodos `cache` y `redis` siempre existen en la configuración procesada, incluso si el usuario no los define.

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
