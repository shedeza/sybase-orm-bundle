# Manual de Operación — Troubleshooting

[← Anterior: Monitorización](04-monitorizacion.md) | [Índice](../README.md) | [Siguiente: Mantenimiento →](06-mantenimiento.md)

---

## Errores de Conexión

### Error: "could not find driver"

```
PDOException: could not find driver
```

**Causa:** La extensión `pdo_dblib` no está instalada o habilitada.

**Solución:**
```bash
# Verificar extensiones cargadas
php -m | grep pdo

# Instalar la extensión (Ubuntu/Debian)
sudo apt-get install php-sybase
sudo phpenmod pdo_dblib
sudo systemctl restart php8.2-fpm

# Verificar
php -m | grep pdo_dblib
```

---

### Error: "Unable to connect to server"

```
PDOException: SQLSTATE[01002] Unable to connect: Adaptive Server is unavailable or does not exist
```

**Causas posibles:**
1. Servidor Sybase no está corriendo
2. Host/puerto incorrectos
3. Firewall bloqueando la conexión
4. FreeTDS no configurado correctamente

**Diagnóstico:**
```bash
# 1. Verificar conectividad de red
telnet db-server 5000
# o
nc -zv db-server 5000

# 2. Probar con tsql directamente
tsql -H db-server -p 5000 -U sa -P password

# 3. Verificar configuración FreeTDS
cat /etc/freetds/freetds.conf
tsql -C  # Ver configuración compilada

# 4. Verificar DNS
nslookup db-server
```

---

### Error: "Login failed"

```
PDOException: SQLSTATE[28000] Login failed for user 'sa'
```

**Causas:**
- Credenciales incorrectas
- Usuario bloqueado en Sybase ASE
- La cuenta ha expirado

**Solución:**
```bash
# Verificar credenciales con tsql
tsql -H db-server -p 5000 -U sa -P password_correcta

# En Sybase ASE (con isql o cuenta admin):
# sp_locklogin 'usuario', 'unlock'
```

---

### Error: "Connection timed out"

```
PDOException: SQLSTATE[HYT00] [TCP/IP] timeout expired
```

**Causas:**
- Servidor Sybase sobrecargado
- Red lenta o inestable
- Timeout de FreeTDS demasiado bajo

**Solución:**
```ini
# /etc/freetds/freetds.conf
[global]
    connect timeout = 30
    timeout = 120
```

```ini
# php.ini
pdo_dblib.timeout = 120
```

---

## Errores de Charset

### Error: "Conversion from UTF-8 to ISO-8859-1 failed"

**Causa:** La base de datos tiene caracteres que no se pueden convertir.

**Solución:**
```yaml
# Deshabilitar conversión si no es necesaria
sybase_orm:
    connection:
        charset_conversion: false
        charset: 'ISO-8859-1'
```

O limpiar los datos problemáticos en la base de datos.

---

### Caracteres extraños en resultados (mojibake)

**Causa:** Mismatch entre el charset de la conexión y el de los datos.

**Diagnóstico:**
```bash
# Verificar charset de FreeTDS
tsql -C | grep charset

# Verificar charset del servidor
# En Sybase ASE:
# SELECT @@client_csname, @@ncharset
```

**Solución:**
1. Asegurar que `charset` coincide con el charset del servidor
2. Si el servidor usa ISO-8859-1 y la app necesita UTF-8, activar `charset_conversion: true`

---

## Errores de Esquema

### Error: "Table does not exist"

```
[App\Entity\Order] Table "orders" does not exist in database.
```

**Causa:** La tabla mapeada en la entidad no existe en la base de datos.

**Solución:**
```bash
# Verificar si la tabla existe
php bin/console sybase:schema:validate

# Generar y ejecutar migraciones
php bin/console sybase:migrate:generate
php bin/console sybase:migrate
```

---

### Error: "Column not found"

```
[App\Entity\Product] Column "sku" not found in table "products".
```

**Causa:** La columna mapeada no existe en la tabla.

**Solución:**
1. Verificar el nombre de la columna en el atributo `#[Column(name: '...')]`
2. Generar una migración para añadir la columna
3. O corregir el nombre en el mapping

---

## Errores de Caché

### Datos desactualizados después de cambios directos en BD

**Causa:** La caché de segundo nivel tiene datos que no reflejan cambios directos en la base de datos.

**Solución:**
```bash
php bin/console sybase:cache:clear
```

O desde código:
```php
$this->cacheManager->clear();
```

---

### Error: "Class not found" para proxy

```
Error: Class 'App\Entity\Proxy\ProductProxy' not found
```

**Causa:** Los proxies no están generados.

**Solución:**
```bash
php bin/console sybase:proxy:generate
```

---

## Errores del Bundle

### "There are no commands defined in the sybase namespace"

**Causa:** El bundle no está registrado o no hay conexión configurada.

**Solución:**
```bash
# Verificar que el bundle está registrado
grep -r "SybaseORMBundle" config/bundles.php

# Verificar que hay configuración
cat config/packages/sybase_orm.yaml

# Si falta, ejecutar instalación
php bin/console sybase:install
```

---

### "cache:clear fails after installing bundle"

**Causa:** El bundle espera una configuración de conexión válida.

**Solución:** El bundle maneja esto gracefully — si no hay conexión configurada, no registra servicios. Si aún falla:

```bash
# Limpiar caché manualmente
rm -rf var/cache/*
php bin/console cache:clear
```

---

## Errores de Rendimiento

### N+1 Queries

**Síntoma:** Muchas queries similares en el profiler (e.g., 50 SELECT para una lista de 50 items).

**Causa:** Acceso lazy a relaciones dentro de un loop.

**Solución:** Cargar relaciones de antemano o usar queries que incluyan joins.

---

### Queries lentas

**Diagnóstico:**
1. Revisar el panel del profiler para identificar queries lentas
2. Ejecutar `SET SHOWPLAN ON` en Sybase ASE para ver el plan de ejecución
3. Verificar que existen índices apropiados

**Solución:**
```sql
-- En Sybase ASE: crear índice
CREATE INDEX idx_products_category ON products(category_id)

-- Verificar plan de ejecución
SET SHOWPLAN ON
GO
SELECT * FROM products WHERE category_id = 5
GO
SET SHOWPLAN OFF
GO
```

---

## Tabla Resumen de Errores

| Error | Causa más común | Solución rápida |
|-------|----------------|-----------------|
| `could not find driver` | pdo_dblib no instalada | `apt install php-sybase` |
| `Unable to connect` | Servidor inaccesible | Verificar red y FreeTDS |
| `Login failed` | Credenciales incorrectas | Verificar .env |
| `Connection timed out` | Timeout bajo o red lenta | Aumentar timeout en freetds.conf |
| `Table does not exist` | Migración pendiente | `sybase:migrate` |
| `Column not found` | Mapping incorrecto | Verificar `#[Column]` |
| `Class not found` (proxy) | Proxies no generados | `sybase:proxy:generate` |
| Datos desactualizados | Caché obsoleta | `sybase:cache:clear` |
| Caracteres extraños | Charset mismatch | Configurar `charset_conversion` |

---

[← Anterior: Monitorización](04-monitorizacion.md) | [Índice](../README.md) | [Siguiente: Mantenimiento →](06-mantenimiento.md)
