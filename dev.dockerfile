FROM php:8.1 AS php
RUN apt update && apt install -y unzip zip wget
WORKDIR /root
# Install composer
RUN wget https://raw.githubusercontent.com/composer/getcomposer.org/cd8ca011326ab9a17a555846c69461c1d53c1895/web/installer -O - -q | php -- --quiet

FROM php AS prod_dependencies
# Install only production dependencies
WORKDIR /src
ADD composer.json /src/
ADD composer.lock /src/
RUN /root/composer.phar install --no-dev
ADD . /src

FROM php AS dev_dependencies
# Install all dependencies (including dev)
WORKDIR /src
ADD composer.json /src/
ADD composer.lock /src/
RUN /root/composer.phar install
ADD . /src

FROM dev_dependencies AS test
RUN /src/vendor/bin/phpunit --process-isolation tests

FROM dev_dependencies AS lint
WORKDIR /src
RUN /src/vendor/bin/phpcs --config-set installed_paths ../../phpcsstandards/phpcsextra,../../phpcsstandards/phpcsutils,../../wp-coding-standards/wpcs
RUN /src/vendor/bin/phpcs -ps --standard=WordPress class-duouniversal-*.php

FROM prod_dependencies AS package
WORKDIR /src
RUN ./package.sh