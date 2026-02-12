FROM dunglas/frankenphp:1.4-php8.4-bookworm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql zip pcntl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Node.js 22 for frontend build
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first (cache layer)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader

# Copy package files (cache layer)
COPY package.json package-lock.json ./
RUN npm ci

# Copy application code
COPY . .

# Build frontend assets
RUN npm run build

# Cache routes and views (NOT config — env vars aren't available at build time)
RUN php artisan route:cache \
    && php artisan view:cache

# Set permissions
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

EXPOSE ${PORT:-8080}

CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
