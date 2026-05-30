# =============================================================================
# Statflow — Backend image (FrankenPHP / Symfony)
#
# Build targets:
#   dev   — development, expects source bind-mounted at /app
#   prod  — self-contained optimised build
#
# PHP extensions installed:
#   pdo_pgsql  — PostgreSQL persistence
#   intl       — locale / ICU
#   opcache    — bytecode cache (worker mode uses it heavily)
#   xdebug     — code-coverage driver (PHPUnit ≥90 % gate; dev only)
#               Note: FrankenPHP uses a ZTS (thread-safe) PHP build; PCOV does
#               not support ZTS, so Xdebug is the correct coverage driver here.
#   zip        — Composer artefacts
#   apcu       — in-process shared memory cache
#   redis      — Redis Streams client (via PECL)
# =============================================================================

# ── Composer binary ───────────────────────────────────────────────────────────
FROM composer:2.10 AS composer-bin

# ── Shared base: FrankenPHP with all required extensions ─────────────────────
FROM dunglas/frankenphp:1.12-php8.3 AS base

LABEL org.opencontainers.image.title="Statflow Backend" \
      org.opencontainers.image.description="FrankenPHP + Symfony API for the Statflow analytics platform" \
      org.opencontainers.image.licenses="AGPL-3.0"

# System packages needed for PHP extension compilation and runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        libpq-dev \
        unzip \
        git \
    && rm -rf /var/lib/apt/lists/*

# Install bundled PHP extensions
RUN install-php-extensions \
        pdo_pgsql \
        intl \
        zip \
        opcache \
        apcu

# Install redis via PECL (not bundled in the FrankenPHP helpers)
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear

# Bring in the Composer binary from the official image
COPY --from=composer-bin /usr/bin/composer /usr/bin/composer

# Dedicated non-root user (matches common Symfony convention)
RUN groupadd --gid 1001 statflow \
    && useradd --uid 1001 --gid statflow --shell /bin/bash --create-home statflow

# OPcache settings tuned for FrankenPHP worker mode
COPY docker/backend/php-opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# APCu settings
COPY docker/backend/php-apcu.ini /usr/local/etc/php/conf.d/apcu.ini

WORKDIR /app

# ── Development target ────────────────────────────────────────────────────────
# Source is bind-mounted; image contains only the runtime environment.
# Caddyfile is baked into the image from docker/backend/backend.Caddyfile.
FROM base AS dev

ENV APP_ENV=dev \
    APP_DEBUG=1 \
    COMPOSER_HOME=/home/statflow/.composer

# Install Xdebug for PHPUnit code-coverage (required for the ≥90 % gate).
# FrankenPHP uses a ZTS (thread-safe) PHP build; PCOV does not support ZTS,
# so Xdebug is the correct coverage driver. Installed in the dev stage only.
#
# We use pecl directly (as with redis) because install-php-extensions performs
# a post-install load check via `php -r 'extension_loaded(...)'` which fails for
# Zend extensions in ZTS builds (they require `zend_extension=` in php.ini,
# not `extension=`). The echo step writes the correct ini snippet instead.
RUN pecl install xdebug \
    && echo "zend_extension=xdebug.so" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && rm -rf /tmp/pear

# Make the mount-point owned by statflow so bind-mount works without root
RUN chown statflow:statflow /app

# Bake the Caddyfile into the image — avoids fragile single-file bind-mount
COPY docker/backend/backend.Caddyfile /app/Caddyfile

USER statflow

# FrankenPHP in worker mode; --watch reloads PHP workers on source changes
CMD ["frankenphp", "run", "--config", "/app/Caddyfile", "--watch"]

# ── Production target ─────────────────────────────────────────────────────────
FROM base AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0

# Copy application source (path relative to build context = repo root)
COPY --chown=statflow:statflow apps/backend /app

# Bake the Caddyfile into the image
COPY docker/backend/backend.Caddyfile /app/Caddyfile

USER statflow

# Install production dependencies (no dev packages, optimised autoloader)
RUN composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --prefer-dist

CMD ["frankenphp", "run", "--config", "/app/Caddyfile"]
