FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    git \
    unzip \
    && docker-php-ext-install curl \
    && docker-php-ext-install sockets \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite
RUN a2enmod headers

# Configure Apache for SSE
RUN echo "LoadModule headers_module modules/mod_headers.so" >> /etc/apache2/apache2.conf
RUN echo "<Location "/chat-unified.php">" >> /etc/apache2/apache2.conf
RUN echo "    SetEnv no-gzip 1" >> /etc/apache2/apache2.conf
RUN echo "    SetEnv no-buffer 1" >> /etc/apache2/apache2.conf
RUN echo "</Location>" >> /etc/apache2/apache2.conf

# Configure PHP for streaming
RUN echo "output_buffering = Off" >> /usr/local/etc/php/php.ini
RUN echo "zlib.output_compression = Off" >> /usr/local/etc/php/php.ini
RUN echo "implicit_flush = On" >> /usr/local/etc/php/php.ini
RUN echo "max_execution_time = 300" >> /usr/local/etc/php/php.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Create logs directory
RUN mkdir -p logs && chown www-data:www-data logs

# Install PHP dependencies (if composer.json exists)
RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader; fi

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Start Apache
CMD ["apache2-foreground"]
