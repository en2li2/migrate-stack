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

# Kimlik OCR: tesseract (TR dil paketi) + ImageMagick 7 (magick) + ghostscript.
# IM7 PDF politikası varsayılan kapalı → PDF kimlik okunabilsin diye açılır.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
      tesseract-ocr \
      tesseract-ocr-tur \
      imagemagick \
      ghostscript \
    && (sed -i 's/rights="none" pattern="PDF"/rights="read|write" pattern="PDF"/' /etc/ImageMagick-7/policy.xml || true) \
    && rm -rf /var/lib/apt/lists/*

# Composer (proje aşamasında lazım olacak)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
