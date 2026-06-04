# Manual de Operación — Despliegue

[← Anterior: Requisitos](01-requisitos-infraestructura.md) | [Índice](../README.md) | [Siguiente: Configuración de Entornos →](03-configuracion-entornos.md)

---

## Proceso de Deploy

El siguiente flujo describe los pasos para un despliegue correcto en producción:

```mermaid
flowchart TD
    A[Pull código] --> B[composer install --no-dev]
    B --> C[Configurar variables de entorno]
    C --> D[php bin/console cache:clear --env=prod]
    D --> E[php bin/console sybase:proxy:generate]
    E --> F[php bin/console sybase:migrations:migrate --env=prod]
    F --> G[php bin/console sybase:schema:validate --env=prod]
    G --> H[Verificar estado]
    H --> I[Activar nuevo release]
```

## Pasos Detallados

### 1. Obtener el código

```bash
cd /var/www/app
git pull origin main
# O para deploy atómico con symlinks:
git clone --branch main git@github.com:org/app.git /var/www/releases/$(date +%Y%m%d%H%M%S)
```

### 2. Instalar dependencias

```bash
composer install --no-dev --optimize-autoloader --classmap-authoritative
```

Flags importantes:
- `--no-dev`: No instalar dependencias de desarrollo
- `--optimize-autoloader`: Genera classmap optimizado
- `--classmap-authoritative`: Solo busca en classmap (más rápido)

### 3. Configurar variables de entorno

Asegurarse de que todas las variables están definidas en el entorno de producción:

```bash
# Verificar que DATABASE_URL está definida
echo $DATABASE_URL
```

Alternativa: usar un archivo `.env.local` (no versionado):

```dotenv
DATABASE_URL="sybase://app_user:SecurePass123@prod-db.internal:5000/production?charset=UTF-8"
```

### 4. Limpiar caché

```bash
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod
```

### 5. Generar proxies

```bash
php bin/console sybase:proxy:generate --env=prod
```

Esto genera las clases proxy de lazy loading en el directorio de caché. En producción, los proxies deben estar pre-generados para evitar generación on-demand.

### 6. Ejecutar migraciones

```bash
php bin/console sybase:migrations:migrate --env=prod
```

> **Importante:** Siempre ejecuta migraciones antes de activar el nuevo código. Si una migración falla, el código antiguo sigue activo.

### 7. Validar esquema (opcional pero recomendado)

```bash
php bin/console sybase:schema:validate --env=prod
```

Si reporta errores, investiga antes de activar el release.

### 8. Activar el release

```bash
# Con deploy atómico por symlinks
ln -sfn /var/www/releases/20240115143022 /var/www/current

# Recargar PHP-FPM
sudo systemctl reload php8.2-fpm
```

## Script de Deploy Ejemplo

```bash
#!/bin/bash
set -e

RELEASE_DIR="/var/www/releases/$(date +%Y%m%d%H%M%S)"
CURRENT_LINK="/var/www/current"

echo "==> Cloning repository..."
git clone --branch main --depth 1 git@github.com:org/app.git "$RELEASE_DIR"

echo "==> Installing dependencies..."
cd "$RELEASE_DIR"
composer install --no-dev --optimize-autoloader --classmap-authoritative

echo "==> Clearing cache..."
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod

echo "==> Generating proxies..."
php bin/console sybase:proxy:generate --env=prod

echo "==> Running migrations..."
php bin/console sybase:migrations:migrate --env=prod

echo "==> Validating schema..."
php bin/console sybase:schema:validate --env=prod

echo "==> Switching release..."
ln -sfn "$RELEASE_DIR" "$CURRENT_LINK"

echo "==> Reloading PHP-FPM..."
sudo systemctl reload php8.2-fpm

echo "==> Clearing ORM cache..."
php bin/console sybase:cache:clear --env=prod

echo "==> Deploy complete!"
```

## Deploy con Docker

### Dockerfile de producción

```dockerfile
FROM php:8.2-fpm-alpine

# Instalar extensiones
RUN apk add --no-cache freetds-dev \
    && docker-php-ext-install pdo_dblib opcache

# Copiar configuración de FreeTDS
COPY docker/freetds.conf /etc/freetds/freetds.conf

# Copiar configuración PHP
COPY docker/php-prod.ini $PHP_INI_DIR/conf.d/99-production.ini

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copiar aplicación
WORKDIR /var/www
COPY . .

# Instalar dependencias
RUN composer install --no-dev --optimize-autoloader --classmap-authoritative

# Build steps
RUN php bin/console cache:clear --env=prod --no-warmup \
    && php bin/console cache:warmup --env=prod \
    && php bin/console sybase:proxy:generate --env=prod

# Permisos
RUN chown -R www-data:www-data var/

EXPOSE 9000
CMD ["php-fpm"]
```

### Docker Compose (producción)

```yaml
services:
  app:
    build: .
    environment:
      - DATABASE_URL=sybase://app:pass@sybase-server:5000/prod_db
      - APP_ENV=prod
    volumes:
      - ./var/log:/var/www/var/log
    depends_on:
      - redis

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
```

## Rollback

Si un deploy falla:

```bash
# Volver al release anterior (deploy atómico)
ls -la /var/www/releases/ | tail -2  # ver releases disponibles
ln -sfn /var/www/releases/YYYYMMDDHHMMSS_anterior /var/www/current
sudo systemctl reload php8.2-fpm
```

Para migraciones, actualmente no hay rollback automático. Se recomienda:
1. Tener backups antes de migrar
2. Preparar scripts SQL de rollback manuales para migraciones críticas

## CI/CD: GitHub Actions

```yaml
name: Deploy
on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Deploy to production
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.PROD_HOST }}
          username: deploy
          key: ${{ secrets.SSH_KEY }}
          script: |
            cd /var/www/current
            git pull origin main
            composer install --no-dev --optimize-autoloader
            php bin/console cache:clear --env=prod
            php bin/console sybase:proxy:generate --env=prod
            php bin/console sybase:migrations:migrate --env=prod
            sudo systemctl reload php8.2-fpm
```

---

[← Anterior: Requisitos](01-requisitos-infraestructura.md) | [Índice](../README.md) | [Siguiente: Configuración de Entornos →](03-configuracion-entornos.md)
