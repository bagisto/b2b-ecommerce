#!/usr/bin/env bash
#
# Boots a published Bagisto image with this package installed, for the Playwright suite.
#
# Required: BAGISTO_IMAGE   e.g. webkul/bagisto:2.5.0-nginx-mysql
# Optional: BAGISTO_CONTAINER (bagisto), BAGISTO_PORT (8080), MAILPIT_PORT (8025),
#           PACKAGE_DIR (the repository root), PACKAGE_VERSION (3.0.0)
#
# The image already runs on Asia/Kolkata; APP_TIMEZONE is not passed because the image's
# entrypoint cannot write a value containing a slash. The PHP runtime is restarted after the
# install because the image's opcache never revalidates the files the install changes.
#
# Mailpit shares the container's network, so the mail the image sends to 127.0.0.1:2525 lands
# in Mailpit without changing any Bagisto setting; its API is published on MAILPIT_PORT.

set -euo pipefail

: "${BAGISTO_IMAGE:?Set BAGISTO_IMAGE, e.g. webkul/bagisto:2.5.0-nginx-mysql}"

CONTAINER="${BAGISTO_CONTAINER:-bagisto}"
PORT="${BAGISTO_PORT:-8080}"
MAILPIT_PORT="${MAILPIT_PORT:-8025}"
APP_URL="http://127.0.0.1:${PORT}"
PACKAGE_DIR="${PACKAGE_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
PACKAGE_VERSION="${PACKAGE_VERSION:-3.0.0}"
APP_DIR=/var/www/bagisto
PACKAGE_MOUNT=/opt/b2b-suite

echo "::group::Start Bagisto Image (${BAGISTO_IMAGE})"
docker run -d \
    --name "${CONTAINER}" \
    -p "${PORT}:80" \
    -p "${MAILPIT_PORT}:8025" \
    -e APP_URL="${APP_URL}" \
    "${BAGISTO_IMAGE}"

docker run -d \
    --name "${CONTAINER}-mailpit" \
    --network "container:${CONTAINER}" \
    -e MP_SMTP_BIND_ADDR=0.0.0.0:2525 \
    -e MP_UI_BIND_ADDR=0.0.0.0:8025 \
    axllent/mailpit

curl --silent --fail --output /dev/null \
    --retry 60 --retry-delay 2 --retry-all-errors \
    "${APP_URL}/"

curl --silent --fail --output /dev/null \
    --retry 30 --retry-delay 2 --retry-all-errors \
    "http://127.0.0.1:${MAILPIT_PORT}/api/v1/info"
echo "::endgroup::"

echo "::group::Copy Package Into Container"
docker exec "${CONTAINER}" mkdir -p "${PACKAGE_MOUNT}"

tar -C "${PACKAGE_DIR}" \
    --exclude=./.git \
    --exclude=./node_modules \
    --exclude=./tests \
    -cf - . | docker exec -i "${CONTAINER}" tar -C "${PACKAGE_MOUNT}" -xf -
echo "::endgroup::"

echo "::group::Install B2B Suite (${PACKAGE_VERSION})"
docker exec -w "${APP_DIR}" "${CONTAINER}" composer config repositories.b2b-suite \
    "{\"type\":\"path\",\"url\":\"${PACKAGE_MOUNT}\",\"options\":{\"symlink\":true,\"versions\":{\"bagisto/b2b-suite\":\"${PACKAGE_VERSION}\"}}}"

docker exec -w "${APP_DIR}" "${CONTAINER}" composer require \
    "bagisto/b2b-suite:${PACKAGE_VERSION}" \
    --update-no-dev --no-interaction --no-progress

docker exec -w "${APP_DIR}" "${CONTAINER}" sed -i \
    's/^];$/    Webkul\\B2BSuite\\Providers\\B2BSuiteServiceProvider::class,\n];/' \
    bootstrap/providers.php

docker exec -w "${APP_DIR}" "${CONTAINER}" grep -q 'B2BSuiteServiceProvider' bootstrap/providers.php

docker exec -w "${APP_DIR}" "${CONTAINER}" php artisan optimize:clear --no-interaction
docker exec -w "${APP_DIR}" "${CONTAINER}" php artisan b2b-suite:install --no-interaction
docker exec -w "${APP_DIR}" "${CONTAINER}" php artisan optimize --no-interaction
docker exec -w "${APP_DIR}" "${CONTAINER}" chown -R www-data:www-data storage bootstrap/cache
echo "::endgroup::"

echo "::group::Reload PHP Runtime"
docker exec "${CONTAINER}" sh -c \
    'for program in php-fpm apache2 lsphp openlitespeed; do
        if supervisorctl status "$program" >/dev/null 2>&1; then
            supervisorctl restart "$program";
        fi;
    done'

curl --silent --fail --output /dev/null \
    --retry 30 --retry-delay 2 --retry-all-errors \
    "${APP_URL}/customer/register?type=company"

curl --silent --fail --output /dev/null "${APP_URL}/admin/login"
echo "::endgroup::"

echo "Bagisto is ready at ${APP_URL} with bagisto/b2b-suite ${PACKAGE_VERSION}."
