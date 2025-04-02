# Usa la imagen oficial de PHP 8.2 con FastCGI Process Manager (FPM)
FROM php:8.2-fpm

# Define argumentos para el usuario y su ID (se pueden pasar al construir la imagen)
ARG user=laravel
ARG uid=1000

# Actualiza la lista de paquetes e instala dependencias necesarias
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    zip \
    redis \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip pdo_mysql mbstring exif pcntl bcmath gd opcache intl sockets \
    && pecl install redis \
    && docker-php-ext-enable redis zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copia Composer desde su imagen oficial y lo instala en el contenedor
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Crea un nuevo usuario con el ID especificado y lo asigna al grupo www-data
RUN echo "Creando usuario con UID=${uid} y nombre=${user}" && \
    useradd -u ${uid} -ms /bin/bash -g www-data ${user}

# Crea el directorio de la aplicación y asigna los permisos correctos
WORKDIR /var/www

# Copia los archivos completos de la aplicación al contenedor en /var/www
COPY --chown=$user:www-data . /var/www

# Copia composer.json y composer.lock primero para aprovechar la caché
COPY composer.json composer.lock /var/www/

# Verifica que la extensión ZIP está habilitada
RUN php -m | grep -i zip || (echo "❌ ZIP NO ESTÁ INSTALADO" && exit 1)

# Instala las dependencias de Composer, deshabilita la validación de plataforma
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Ajusta los permisos para evitar problemas de acceso
RUN chown -R $user:www-data /var/www

# Cambia el usuario por defecto para ejecutar procesos con el usuario creado
USER $user

# Expone el puerto 9000 (utilizado por PHP-FPM)
EXPOSE 9000

# Comando por defecto para iniciar PHP-FPM
CMD ["php-fpm"]
