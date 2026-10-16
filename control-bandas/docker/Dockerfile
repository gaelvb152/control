# Entorno de desarrollo local: PHP 8.3 + Apache (igual que el hosting: lee .htaccess)
FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev libjpeg-dev libwebp-dev unzip git \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd \
    && a2enmod rewrite headers \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

# Configuración de PHP para desarrollo (zona horaria de Hermosillo: sin horario de verano)
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY docker/php-dev.ini "$PHP_INI_DIR/conf.d/99-control-bandas.ini"

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
