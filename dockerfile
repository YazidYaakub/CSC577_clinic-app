# Use official PHP image with Apache
FROM php:8.2-apache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy project files into the web root
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html
RUN chown www-data:www-data /var/www/html/database/healthcare.sqlite && \
    chmod 664 /var/www/html/database/healthcare.sqlite

# Expose port 80 (Render will use this automatically)
EXPOSE 80

