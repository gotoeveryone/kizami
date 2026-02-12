FROM ghcr.io/gotoeveryone/composer-php:8.4

RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    unzip \
    git \
    && docker-php-ext-install \
    pdo_mysql \
    intl \
    zip \
    opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
