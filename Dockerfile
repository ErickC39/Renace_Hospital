# Imagen del aplicativo para el servidor público (Render).
# Las credenciales NO van aquí: se configuran como variables de entorno en Render.

# 1. Dependencias de PHP: Composer solo corre durante la construcción.
FROM composer:2 AS dependencias
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative

# 2. Servidor: PHP 8.2 + Apache + OCI8 con Oracle Instant Client.
FROM php:8.2-apache-bookworm

ENV APP_DEBUG=false \
    APP_TRUST_PROXY=true \
    PORT=10000

ARG IC_BASE=https://download.oracle.com/otn_software/linux/instantclient/2119000
ARG IC_VERSION=21.19.0.0.0dbru

RUN apt-get update \
    && apt-get install -y --no-install-recommends libaio1 unzip \
    && mkdir -p /opt/oracle && cd /opt/oracle \
    && curl -fsSLO "${IC_BASE}/instantclient-basiclite-linux.x64-${IC_VERSION}.zip" \
    && curl -fsSLO "${IC_BASE}/instantclient-sdk-linux.x64-${IC_VERSION}.zip" \
    && unzip -qo '*.zip' && rm -f ./*.zip \
    && mv instantclient_* instantclient \
    && echo /opt/oracle/instantclient > /etc/ld.so.conf.d/oracle.conf && ldconfig \
    && docker-php-ext-configure oci8 --with-oci8=instantclient,/opt/oracle/instantclient \
    && docker-php-ext-install oci8 opcache \
    && rm -rf /var/lib/apt/lists/*

# PHP de producción (no muestra errores en pantalla).
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Velocidad: PHP guarda el código ya compilado y cierra las conexiones a Oracle sin uso.
RUN printf 'opcache.validate_timestamps=0\nopcache.memory_consumption=48\noci8.persistent_timeout=900\n' > "$PHP_INI_DIR/conf.d/hospital.ini"

# Apache sirve solo public/ y escucha en el puerto que asigna Render.
RUN a2enmod rewrite headers \
    && sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/hospital.conf \
    && a2enconf hospital \
    && printf 'KeepAlive Off\n<IfModule mpm_prefork_module>\n    StartServers 3\n    MinSpareServers 2\n    MaxSpareServers 5\n    MaxRequestWorkers 5\n</IfModule>\n' > /etc/apache2/conf-available/hospital-procesos.conf \
    && a2enconf hospital-procesos

COPY --from=dependencias /app /var/www/html

EXPOSE 10000
