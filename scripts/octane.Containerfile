# The Octane lane of scripts/octane-lane.sh: FrankenPHP in worker mode on PHP 8.4.
#   podman build -t lathe-octane -f scripts/octane.Containerfile scripts
FROM docker.io/dunglas/frankenphp:1-php8.4

RUN install-php-extensions pdo_mysql pcntl intl
