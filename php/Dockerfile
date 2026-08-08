FROM php:8.3-fpm

# Laravel için gerekli eklentileri güvenilir biçimde kur (mlocati installer)
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
      exif \
      redis

# Composer (proje aşamasında lazım olacak)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
