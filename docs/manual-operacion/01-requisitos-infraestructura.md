# Manual de Operación — Requisitos de Infraestructura

[← Índice](../README.md) | [Siguiente: Despliegue →](02-despliegue.md)

---

## Requisitos del Sistema

| Componente | Versión Mínima | Notas |
|------------|---------------|-------|
| PHP | 8.1 | 8.2+ recomendado para Symfony 7 |
| Symfony | 6.0 o 7.0 | Framework Bundle requerido |
| Sybase ASE | 15.x | Adaptive Server Enterprise |
| FreeTDS | 0.91+ | Librería de protocolo TDS |
| PDO Extension | pdo_dblib | Driver PHP para Sybase via FreeTDS |

## Instalación de FreeTDS

FreeTDS es la librería que permite la comunicación con Sybase ASE desde Linux/macOS.

### Ubuntu / Debian

```bash
# Instalar FreeTDS y el driver PDO
sudo apt-get update
sudo apt-get install -y freetds-dev freetds-bin php-sybase

# Verificar instalación
tsql -C
```

### CentOS / RHEL / Rocky Linux

```bash
# Instalar FreeTDS
sudo dnf install -y freetds freetds-devel

# Instalar extensión PDO
sudo dnf install -y php-pdo_dblib
```

### macOS (Homebrew)

```bash
brew install freetds
```

### Alpine Linux (Docker)

```dockerfile
RUN apk add --no-cache freetds-dev \
    && docker-php-ext-install pdo_dblib
```

## Configuración de FreeTDS

### Archivo freetds.conf

La configuración de FreeTDS se encuentra generalmente en `/etc/freetds/freetds.conf`:

```ini
# /etc/freetds/freetds.conf

[global]
    # Versión del protocolo TDS (5.0 para Sybase ASE)
    tds version = 5.0
    
    # Charset por defecto
    client charset = UTF-8
    
    # Timeout de conexión en segundos
    connect timeout = 10
    timeout = 60

# Definir servidor(es)
[mi_servidor_sybase]
    host = 192.168.1.100
    port = 5000
    tds version = 5.0
    client charset = UTF-8
```

### Versiones del Protocolo TDS

| Versión TDS | Servidor |
|-------------|----------|
| 4.2 | SQL Server 6.x |
| 5.0 | **Sybase ASE** (usar esta) |
| 7.0 | SQL Server 7.0+ |
| 7.1+ | SQL Server 2000+ |

> **Importante:** Para Sybase ASE siempre usa `tds version = 5.0`.

### Verificar conectividad

```bash
# Probar conexión con tsql
tsql -H 192.168.1.100 -p 5000 -U sa -P password

# O usando el nombre definido en freetds.conf
tsql -S mi_servidor_sybase -U sa -P password
```

Salida esperada:
```
locale is "en_US.UTF-8"
locale charset is "UTF-8"
using default charset "UTF-8"
1>
```

## Extensión PDO pdo_dblib

### Verificar que está instalada

```bash
php -m | grep pdo_dblib
# Debería mostrar: pdo_dblib

# Verificar configuración
php -i | grep -A 5 "pdo_dblib"
```

### Compilar desde fuentes (si es necesario)

```bash
# Descargar fuentes de PHP
cd /usr/src/php-*/ext/pdo_dblib
phpize
./configure --with-pdo-dblib=/usr
make
make install

# Habilitar la extensión
echo "extension=pdo_dblib.so" > /etc/php/8.2/mods-available/pdo_dblib.ini
phpenmod pdo_dblib
```

### Docker: imagen base con pdo_dblib

```dockerfile
FROM php:8.2-fpm

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    freetds-dev \
    freetds-bin \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensión PDO dblib
RUN docker-php-ext-configure pdo_dblib --with-libdir=/lib/x86_64-linux-gnu \
    && docker-php-ext-install pdo_dblib

# Configurar FreeTDS
COPY freetds.conf /etc/freetds/freetds.conf

# Verificar
RUN php -m | grep pdo_dblib
```

## Configuración PHP Recomendada

```ini
; php.ini - Recomendaciones para producción con Sybase
[PHP]
memory_limit = 256M
max_execution_time = 60

[pdo_dblib]
; Timeout de queries en segundos
pdo_dblib.timeout = 60
```

## Requisitos de Red

| Puerto | Protocolo | Uso |
|--------|-----------|-----|
| 5000 (default) | TCP | Conexión al servidor Sybase ASE |
| 6379 (opcional) | TCP | Redis para caché |

Asegúrate de que el firewall permite conexiones TCP desde el servidor de aplicación al servidor Sybase ASE en el puerto configurado.

## Requisitos de Base de Datos

El usuario configurado necesita los siguientes permisos en Sybase ASE:

| Permiso | Para |
|---------|------|
| `SELECT` en `sysobjects` | Schema validation |
| `SELECT` en `syscolumns` | Schema validation |
| `SELECT/INSERT/UPDATE/DELETE` en tablas de negocio | Operaciones CRUD |
| `CREATE TABLE` (opcional) | Migraciones |
| `ALTER TABLE` (opcional) | Migraciones |

---

[← Índice](../README.md) | [Siguiente: Despliegue →](02-despliegue.md)
