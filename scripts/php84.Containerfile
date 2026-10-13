# The PHP 8.4 lane of scripts/matrix.sh. It has no Node, like the CI php job.
#   podman build -t lathe-php84 -f scripts/php84.Containerfile scripts
FROM docker.io/library/php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libpq-dev \
    && docker-php-ext-install pdo_mysql pdo_pgsql zip \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=docker.io/library/composer:2 /usr/bin/composer /usr/local/bin/composer

RUN git config --system --add safe.directory '*'

WORKDIR /app
