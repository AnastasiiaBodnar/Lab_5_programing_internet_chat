FROM php:8.2-fpm
WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    curl \
    gnupg \
&& curl -sL https://deb.nodesource.com/setup_20.x | bash - \
&& apt-get install -y nodejs \
&& pecl install redis \
&& docker-php-ext-enable redis \
&& docker-php-ext-configure intl \
&& docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip intl \
&& apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

EXPOSE 9000

CMD ["php-fpm"]
