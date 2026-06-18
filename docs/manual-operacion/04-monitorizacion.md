# Manual de Operación — Monitorización

[← Anterior: Configuración de Entornos](03-configuracion-entornos.md) | [Índice](../README.md) | [Siguiente: Troubleshooting →](05-troubleshooting.md)

---

## Herramientas de Monitorización

| Herramienta | Entorno | Descripción |
|-------------|---------|-------------|
| Symfony Web Profiler | dev | Toolbar y panel con métricas completas del ORM |
| Logs de Symfony | todos | Monolog con queries y errores |
| InstrumentationCollector | dev | Instrumentación nativa del ORM (queries, hydrations, cache, transactions) |
| SybaseQueryCollector | dev | DataCollector que lee del InstrumentationCollector |
| Health checks | prod | Verificación de conectividad |

## Symfony Web Profiler (Desarrollo)

En el entorno `dev`, el bundle utiliza **instrumentación nativa del ORM** (desde v2.0) para recolectar métricas sin overhead de decoradores. El `SybaseQueryCollector` lee del `InstrumentationCollector` y proporciona información detallada en la debug toolbar y el panel del profiler.

### Información visible en la toolbar

- **Número de queries** ejecutadas en el request
- **Tiempo total** de ejecución de queries
- **Indicador visual** de warnings (queries duplicadas, queries lentas, lazy loads excesivos, rollbacks)

### Panel del Profiler — Métricas globales

| Métrica | Descripción |
|---------|-------------|
| Queries | Total de queries y tiempo acumulado |
| Hydrations | Número de hidrataciones realizadas |
| Identity Map hits/misses | Aciertos y fallos en el mapa de identidad |
| Lazy loads | Cargas lazy ejecutadas (posible indicador N+1) |
| Cache hits/misses/writes | Estadísticas de la caché de segundo nivel |
| Transactions | Transacciones iniciadas |
| Rollbacks | Rollbacks ejecutados |
| Flush time | Tiempo total de operaciones flush |

### Panel del Profiler — Detalle de queries

| Columna | Descripción |
|---------|-------------|
| # | Número secuencial de la query |
| SQL | Sentencia SQL ejecutada |
| Params | Parámetros bound (con VarDumper) |
| Time | Tiempo de ejecución (ms) |
| Connection | Nombre de la conexión utilizada |

### Secciones adicionales

- **Queries duplicadas**: muestra queries SQL repetidas en el mismo request
- **Queries lentas**: queries que superan el umbral de 100ms
- **Estadísticas por conexión**: desglose de queries y tiempo por cada conexión configurada

## Logs

### Configuración de Monolog

El bundle registra servicios con inyección opcional de `LoggerInterface`. Configura un canal específico para Sybase:

```yaml
# config/packages/monolog.yaml
monolog:
    channels: ['sybase']
    handlers:
        sybase:
            type: stream
            path: '%kernel.logs_dir%/sybase.log'
            level: debug
            channels: ['sybase']
```

### Niveles de log

| Nivel | Contenido |
|-------|-----------|
| `DEBUG` | Queries ejecutadas, parámetros, tiempos |
| `INFO` | Conexión establecida, migraciones ejecutadas |
| `WARNING` | Queries lentas, reconexiones |
| `ERROR` | Fallos de conexión, errores de query |
| `CRITICAL` | Corrupción de datos, pérdida de conexión durante transacción |

### Ejemplo de salida de logs

```
[2024-01-15 14:30:22] sybase.DEBUG: Query executed {"sql":"SELECT * FROM products WHERE id = ?","params":1,"time_ms":2.34,"connection":"default"}
[2024-01-15 14:30:22] sybase.WARNING: Slow query detected {"sql":"SELECT * FROM orders WHERE ...","time_ms":1523.45,"connection":"default"}
[2024-01-15 14:30:25] sybase.ERROR: Connection failed {"host":"db-server","port":5000,"error":"Unable to connect to server"}
```

## Métricas de Queries

### Identificar queries lentas

Las queries que excedan un umbral pueden registrarse como warning. Para implementar un monitor de queries lentas:

```php
use SybaseORM\Bundle\DataCollector\SybaseQueryCollector;

class SlowQueryMonitor
{
    private const SLOW_THRESHOLD_MS = 500.0;

    public function __construct(
        private readonly SybaseQueryCollector $collector,
        private readonly LoggerInterface $logger,
    ) {}

    public function checkSlowQueries(): void
    {
        foreach ($this->collector->getQueries() as $query) {
            if ($query['time'] > self::SLOW_THRESHOLD_MS) {
                $this->logger->warning('Slow query detected', [
                    'sql' => $query['sql'],
                    'time_ms' => $query['time'],
                    'connection' => $query['connection'],
                ]);
            }
        }
    }
}
```

## Health Checks

### Endpoint de verificación de conexión

Crea un endpoint que verifique la conectividad con Sybase ASE:

```php
use SybaseORM\Connection\ConnectionManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HealthController
{
    #[Route('/health/database', name: 'health_database')]
    public function database(ConnectionManagerInterface $connection): JsonResponse
    {
        try {
            $stmt = $connection->executeQuery('SELECT 1 AS check_value', []);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return new JsonResponse([
                'status' => 'healthy',
                'database' => 'connected',
                'timestamp' => date('c'),
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'status' => 'unhealthy',
                'database' => 'disconnected',
                'error' => $e->getMessage(),
                'timestamp' => date('c'),
            ], 503);
        }
    }
}
```

### Verificar desde CLI

```bash
# Verificar conexión y esquema
php bin/console sybase:schema:validate --env=prod

# Si retorna 0, la conexión y el esquema están OK
echo $?  # 0 = éxito, 1 = error
```

## Monitorización con Herramientas Externas

### Prometheus / Grafana

Exponer métricas desde el collector:

```php
use Prometheus\CollectorRegistry;

class SybaseMetricsExporter
{
    public function exportMetrics(SybaseQueryCollector $collector): void
    {
        $registry = CollectorRegistry::getDefault();
        
        $queryCounter = $registry->getOrRegisterCounter(
            'sybase_orm', 'queries_total', 'Total queries executed'
        );
        $queryCounter->incBy($collector->getQueryCount());

        $queryDuration = $registry->getOrRegisterHistogram(
            'sybase_orm', 'query_duration_ms', 'Query duration in milliseconds'
        );
        foreach ($collector->getQueries() as $query) {
            $queryDuration->observe($query['time']);
        }
    }
}
```

### Alertas recomendadas

| Métrica | Umbral | Acción |
|---------|--------|--------|
| Queries por request | > 50 | Revisar N+1 queries |
| Tiempo total por request | > 5000ms | Optimizar queries |
| Errores de conexión | > 0 en 5 min | Verificar servidor Sybase |
| Queries lentas | > 1000ms | Añadir índices o optimizar |

## Debugging en Producción

### Habilitar profiler temporalmente

```yaml
# config/packages/prod/web_profiler.yaml (temporal)
web_profiler:
    toolbar: false
    intercept_redirects: false

framework:
    profiler:
        enabled: true
        collect: true
        only_exceptions: false
```

> **Advertencia:** Nunca dejes el profiler habilitado en producción permanentemente. Consume recursos y puede exponer información sensible.

### Logs rotativos

```yaml
# config/packages/prod/monolog.yaml
monolog:
    handlers:
        sybase:
            type: rotating_file
            path: '%kernel.logs_dir%/sybase.log'
            level: warning
            max_files: 14
```

---

[← Anterior: Configuración de Entornos](03-configuracion-entornos.md) | [Índice](../README.md) | [Siguiente: Troubleshooting →](05-troubleshooting.md)
