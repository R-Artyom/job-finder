FROM php:8.2-fpm

ENV APP_PATH=/var/www

WORKDIR $APP_PATH

RUN apt-get update && apt-get install -y \
      apt-utils \
      libpq-dev \
      libpng-dev \
      libzip-dev \
      zip unzip \
      git && \
      docker-php-ext-install pdo_mysql && \
      docker-php-ext-install bcmath && \
      docker-php-ext-install gd && \
      docker-php-ext-install zip && \
      apt-get clean && \
      rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install Xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

COPY ./_docker/app/php.ini /usr/local/etc/php/conf.d/php.ini

ENV COMPOSER_ALLOW_SUPERUSER=1

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Node.js и npm
RUN curl -sL https://deb.nodesource.com/setup_18.x | bash && \
    apt install nodejs -y

# Install pkill
RUN apt-get update && apt-get install -y procps

# Добавить переменные UID/GID и их значения по умолчанию (когда они не указаны в docker-compose.yml)
ARG UID=1000
ARG GID=1000
# Создать новую группу с заданным GID
# Создать пользователя с заданным UID, входящего в эту группу, а также домашнюю директорию (/home/appuser) и оболочку по умолчанию (bash).
# Установить права на рабочую директорию
RUN groupadd -g ${GID} appgroup && \
    useradd -u ${UID} -g ${GID} -ms /bin/bash appuser && \
    chown -R appuser:appgroup $APP_PATH
# Переключиться на непривилегированного пользователя
USER appuser
