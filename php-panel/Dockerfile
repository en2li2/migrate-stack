FROM php:8.4-fpm

# ISP Core paneli (Laravel 13 + Filament v5) için eklentiler
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions \
      pdo_mysql \
      mbstring \
      bcmath \
      gd \
      zip \
      intl \
      opcache \
      pcntl \
      posix \
      exif \
      sodium \
      gmp \
      redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/panel
