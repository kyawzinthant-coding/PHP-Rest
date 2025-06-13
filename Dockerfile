
FROM php:8.2-apache


RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring xml intl zip

# Set the web server's root directory to your project's `public` folder.
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
# This is the step that recreates the 'vendor' directory on the server.
RUN composer install --no-dev --optimize-autoloader

# Set the correct ownership for the web server to write files if needed (e.g., logs, cache).
RUN chown -R www-data:www-data /var/www/html