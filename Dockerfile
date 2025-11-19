# Use official PHP + Apache image
FROM php:8.2-apache

# Install PHP extensions needed for MySQL (mysqli, PDO MySQL)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache modules commonly needed for PHP apps
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy project files into the container
COPY . /var/www/html

# Ensure correct permissions for Apache
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

# Expose HTTP port
EXPOSE 80

# Default command (provided by php:apache)
CMD ["apache2-foreground"]
