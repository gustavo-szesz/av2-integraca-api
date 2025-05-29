FROM php:8.0-apache

WORKDIR /var/www/html

# Install dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    && docker-php-ext-install zip mysqli pdo pdo_mysql sockets

# Enable mod_rewrite
RUN a2enmod rewrite

# Copy composer.json first to leverage Docker cache
COPY composer.json composer.lock* ./

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install dependencies
RUN composer install --no-scripts --no-autoloader

# Copy the rest of the application
COPY . .

# Generate autoloader
RUN composer dump-autoload --optimize

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Change Apache port
RUN sed -i 's/80/3003/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Configure virtual host to use .htaccess
RUN sed -i '/<Directory \/var\/www\/html>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

EXPOSE 3003

CMD ["apache2-foreground"]