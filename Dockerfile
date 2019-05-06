FROM php:7.2-cli
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug
RUN curl --silent --show-error https://getcomposer.org/installer | php
COPY . .
RUN php composer.phar install
CMD ["php", "composer.phar", "test"]
