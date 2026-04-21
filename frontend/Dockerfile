# Use the official PHP image with Apache
FROM php:8.2-apache

# Enable Apache mod_rewrite for routing
RUN a2enmod rewrite

# Copy your frontend files into the server directory
COPY . /var/www/html/

# Expose port 80 for Render's routing
EXPOSE 80