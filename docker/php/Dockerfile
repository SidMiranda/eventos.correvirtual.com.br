FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    # GD entrou em 2026-08-29 junto com o upload de imagem do painel: sem ele
    # `UploadedFile::fake()->image()` nem roda ("GD extension is not installed"),
    # e qualquer tratamento de imagem futuro (redimensionar, gerar miniatura)
    # também dependeria dele.
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_pgsql pdo_mysql mbstring bcmath zip gd exif

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

WORKDIR /var/www

# Rodar como root em DEV (resolve problema de volume Windows)
USER root

EXPOSE 9000
CMD ["php-fpm"]