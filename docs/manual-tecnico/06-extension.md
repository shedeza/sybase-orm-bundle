# Manual Técnico — Puntos de Extensión

[← Anterior: Profiler](05-profiler.md) | [Índice](../README.md)

---

## Mecanismos de Extensión

El bundle ofrece varios mecanismos para extender su funcionalidad sin modificar el código fuente:

1. **Hooks del ORM** — Callbacks en el ciclo de vida de entidades
2. **Symfony Event Dispatcher** — Integración con el sistema de eventos de Symfony
3. **Compiler Passes Custom** — Modificar servicios en tiempo de compilación
4. **Override de Servicios** — Reemplazar implementaciones por defecto
5. **Decoradores** — Envolver servicios existentes

## 1. Hooks del ORM

El `HookDispatcher` permite registrar callbacks que se ejecutan en puntos específicos del ciclo de vida:

```php
use SybaseORM\Hook\HookDispatcher;

class AuditHookSubscriber
{
    public function __construct(
        private readonly HookDispatcher $hookDispatcher,
    ) {
        // Registrar hooks
        $this->hookDispatcher->addHook('PreInsert', [$this, 'onPreInsert']);
        $this->hookDispatcher->addHook('PostUpdate', [$this, 'onPostUpdate']);
    }

    public function onPreInsert(object $entity): void
    {
        if (method_exists($entity, 'setCreatedAt')) {
            $entity->setCreatedAt(new \DateTimeImmutable());
        }
    }

    public function onPostUpdate(object $entity): void
    {
        // Lógica de auditoría
    }
}
```

### Hooks disponibles

| Hook | Momento | Datos |
|------|---------|-------|
| `PreFlush` | Antes de iniciar el flush | — |
| `PostFlush` | Después del commit exitoso | — |
| `PreInsert` | Antes del INSERT | Entity |
| `PostInsert` | Después del INSERT | Entity |
| `PreUpdate` | Antes del UPDATE | Entity |
| `PostUpdate` | Después del UPDATE | Entity |
| `PreDelete` | Antes del DELETE | Entity |
| `PostDelete` | Después del DELETE | Entity |

## 2. Symfony Event Dispatcher

El bundle registra un `SymfonyEventDispatcherSubscriber` que publica eventos del ORM en el EventDispatcher de Symfony (si está disponible):

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EntityLifecycleSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'sybase_orm.pre_insert' => 'onPreInsert',
            'sybase_orm.post_flush' => 'onPostFlush',
        ];
    }

    public function onPreInsert($event): void
    {
        // Lógica antes de insertar
    }

    public function onPostFlush($event): void
    {
        // Lógica después del flush (e.g., invalidar caché externa)
    }
}
```

Registrar en `services.yaml`:

```yaml
services:
    App\EventSubscriber\EntityLifecycleSubscriber:
        tags: ['kernel.event_subscriber']
```

## 3. Compiler Passes Custom

Puedes crear compiler passes para modificar los servicios del bundle en tiempo de compilación:

```php
// src/DependencyInjection/CustomSybaseCompilerPass.php
namespace App\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CustomSybaseCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Ejemplo: agregar un logger custom al connection manager
        if ($container->has('sybase_orm.connection_manager.default')) {
            $definition = $container->getDefinition('sybase_orm.connection_manager.default');
            // Modificar argumentos, añadir method calls, etc.
        }
    }
}
```

Registrar en el Kernel:

```php
// src/Kernel.php
protected function build(ContainerBuilder $container): void
{
    $container->addCompilerPass(new CustomSybaseCompilerPass());
}
```

## 4. Override de Servicios

Para reemplazar una implementación completa, define tu propia clase que implemente la interfaz y registra un alias:

```yaml
# config/services.yaml
services:
    # Override del TypeCaster con implementación custom
    App\ORM\CustomTypeCaster:
        arguments: []

    SybaseORM\Type\TypeCasterInterface:
        alias: App\ORM\CustomTypeCaster
```

```php
namespace App\ORM;

use SybaseORM\Type\TypeCasterInterface;

class CustomTypeCaster implements TypeCasterInterface
{
    public function castToPhp(mixed $value, string $type): mixed
    {
        // Lógica custom de conversión
    }

    public function castToDatabase(mixed $value, string $type): mixed
    {
        // Lógica custom de conversión
    }
}
```

## 5. Decoradores

Para envolver un servicio existente (añadir funcionalidad sin reemplazarlo):

```yaml
# config/services.yaml
services:
    App\ORM\LoggingEntityManager:
        decorates: SybaseORM\ORM\EntityManagerInterface
        arguments:
            $inner: '@.inner'
```

```php
namespace App\ORM;

use SybaseORM\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class LoggingEntityManager implements EntityManagerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $inner,
        private readonly LoggerInterface $logger,
    ) {}

    public function find(string $entityClass, mixed $id): ?object
    {
        $this->logger->debug('Finding entity', [
            'class' => $entityClass,
            'id' => $id,
        ]);

        return $this->inner->find($entityClass, $id);
    }

    // Delegar el resto de métodos a $this->inner
}
```

## 6. Extender el Configuration Tree

Si desarrollas un bundle que extiende sybase-orm-bundle, puedes usar PrependExtensionInterface:

```php
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class MyExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('sybase_orm', [
            'cache' => [
                'enabled' => true,
                'default_ttl' => 7200,
            ],
        ]);
    }
}
```

## Resumen de Puntos de Extensión

| Mecanismo | Uso | Complejidad |
|-----------|-----|-------------|
| Hooks ORM | Ciclo de vida de entidades | Baja |
| Event Dispatcher | Integración con ecosistema Symfony | Baja |
| Compiler Pass | Modificar configuración de servicios | Media |
| Override de servicio | Reemplazar implementación completa | Media |
| Decorador | Envolver funcionalidad existente | Media |
| PrependExtension | Configuración desde otro bundle | Baja |

---

[← Anterior: Profiler](05-profiler.md) | [Índice](../README.md)
