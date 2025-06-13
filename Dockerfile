# Use an official PHP image with an Apache web server
FROM php:8.2-apache

# Install system dependencies and necessary PHP extensions.
# ADDED libxml2-dev HERE to fix the new build error.
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring xml intl zip

# Set the web server's root directory to your project's 'public' folder.
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Enable Apache's mod_rewrite for your API's friendly URLs.
RUN a2enmod rewrite

# Copy Composer from the official image to our image.
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the working directory for the container.
WORKDIR /var/www/html

# Copy all your project files into the container.
COPY . .

# Run Composer to install all dependencies from your composer.json file.
RUN composer install --no-dev --optimize-autoloader

# Set the correct ownership for the web server.
RUN chown -R www-data:www-data /var/www/html