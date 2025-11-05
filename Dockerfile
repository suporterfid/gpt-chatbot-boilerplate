FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    git \
    unzip \
    && docker-php-ext-install sockets \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite
RUN a2enmod headers
RUN a2enmod setenvif

# Configure Apache ServerName to suppress FQDN warnings
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Ensure Apache always serves our PHP entrypoint instead of returning a 403
RUN printf "<Directory /var/www/html>\n    DirectoryIndex index.php default.php index.html\n    FallbackResource /default.php\n</Directory>\n" > /etc/apache2/conf-available/project-directoryindex.conf \
    && a2enconf project-directoryindex

# Enable .htaccess files (AllowOverride All)
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Enable CGI passthrough for Authorization header in the DocumentRoot directory
# This ensures the Authorization header is passed to PHP scripts
RUN sed -i '/<Directory \/var\/www\/>/a\    CGIPassAuth On' /etc/apache2/apache2.conf

# Configure Apache for SSE
RUN echo "<Location \"/chat-unified.php\">" >> /etc/apache2/apache2.conf
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

# Composer runs as root during build; silence warnings and allow git in this repo
ENV COMPOSER_ALLOW_SUPERUSER=1

# Set permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Create logs directory
RUN mkdir -p logs && chown www-data:www-data logs

# Install PHP dependencies (if composer.json exists)
RUN git config --global --add safe.directory /var/www/html \
    && if [ -f composer.json ]; then \
        composer install --no-dev --optimize-autoloader --no-interaction || \
        echo "Warning: Composer install failed - dependencies may be missing"; \
    fi

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Start Apache
CMD ["apache2-foreground"]
