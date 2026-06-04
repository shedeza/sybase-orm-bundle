# Documentación - sybase-orm-bundle

Bienvenido a la documentación completa del bundle `shedeza/sybase-orm-bundle`. Aquí encontrarás toda la información necesaria para instalar, usar, entender y operar este bundle en tus proyectos Symfony.

## Manuales Disponibles

| Manual | Audiencia | Descripción |
|--------|-----------|-------------|
| [📖 Manual de Usuario](manual-usuario/) | Desarrolladores | Guía completa para instalar, configurar y utilizar el bundle |
| [🔧 Manual Técnico](manual-tecnico/) | Contribuidores / Desarrolladores avanzados | Arquitectura interna, servicios DI y puntos de extensión |
| [🚀 Manual de Operación](manual-operacion/) | DevOps / Administradores | Despliegue, monitorización y solución de problemas |

---

## Manual de Usuario

Dirigido a desarrolladores que integran el bundle en sus proyectos Symfony.

1. [Instalación](manual-usuario/01-instalacion.md) — Requisitos, Composer, registro del bundle
2. [Configuración](manual-usuario/02-configuracion.md) — Conexiones, variables de entorno, opciones
3. [Uso Básico](manual-usuario/03-uso-basico.md) — EntityManager, CRUD, queries
4. [Repositorios](manual-usuario/04-repositorios.md) — Repositorios custom y autowiring
5. [Migraciones](manual-usuario/05-migraciones.md) — Generación y ejecución de migraciones
6. [Comandos](manual-usuario/06-comandos.md) — Referencia completa de comandos de consola
7. [Caché](manual-usuario/07-cache.md) — Configuración y gestión de caché

## Manual Técnico

Dirigido a desarrolladores que necesitan entender o extender los internos del bundle.

1. [Arquitectura](manual-tecnico/01-arquitectura.md) — Componentes, capas y dependencias
2. [Contenedor DI](manual-tecnico/02-contenedor-di.md) — Registro de servicios y compiler passes
3. [Configuración Interna](manual-tecnico/03-configuracion-interna.md) — Configuration tree y validación
4. [Ciclo de Vida de una Consulta](manual-tecnico/04-ciclo-vida-consulta.md) — Flujo completo de ejecución
5. [Profiler](manual-tecnico/05-profiler.md) — DataCollector e integración con toolbar
6. [Extensión](manual-tecnico/06-extension.md) — Hooks, events y override de servicios

## Manual de Operación

Dirigido a equipos DevOps y administradores de sistemas.

1. [Requisitos de Infraestructura](manual-operacion/01-requisitos-infraestructura.md) — PHP, extensiones, FreeTDS
2. [Despliegue](manual-operacion/02-despliegue.md) — Pasos de deploy y automatización
3. [Configuración de Entornos](manual-operacion/03-configuracion-entornos.md) — Dev, staging, producción
4. [Monitorización](manual-operacion/04-monitorizacion.md) — Profiler, logs y métricas
5. [Troubleshooting](manual-operacion/05-troubleshooting.md) — Errores comunes y soluciones
6. [Mantenimiento](manual-operacion/06-mantenimiento.md) — Migraciones, backups y actualizaciones

---

## Inicio Rápido

Si es tu primera vez con el bundle, te recomendamos seguir este orden:

1. Revisa los [requisitos de infraestructura](manual-operacion/01-requisitos-infraestructura.md)
2. Sigue la guía de [instalación](manual-usuario/01-instalacion.md)
3. Configura tu [conexión a Sybase ASE](manual-usuario/02-configuracion.md)
4. Aprende el [uso básico](manual-usuario/03-uso-basico.md) del EntityManager
