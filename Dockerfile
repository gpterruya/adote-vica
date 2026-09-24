FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
        unzip \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install \
        mysqli \
        pdo_mysql \
        zip \
        gd \
        intl \
        mbstring \
        xml \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite