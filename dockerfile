# Usa la imagen oficial de PHP 8.3 con FastCGI Process Manager (FPM)
FROM php:8.3-fpm

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
    zip \
    unzip

# Limpia la caché de paquetes de apt para reducir el tamaño de la imagen
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Instala extensiones de PHP necesarias
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Copia Composer desde su imagen oficial y lo instala en el contenedor
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Crea un nuevo usuario con el ID especificado y lo asigna al grupo www-data
RUN echo "Creando usuario con UID=${uid} y nombre=${user}" && \
    useradd -u ${uid} -ms /bin/bash -g www-data ${user}

# Crea el directorio /var/www/public si no existe
RUN mkdir -p /var/www

# Copia los archivos de la aplicación al contenedor en /var/www con los permisos adecuados
COPY --chown=$user:www-data . /var/www

# Cambia el usuario por defecto para ejecutar procesos con el usuario creado
USER $user

# Expone el puerto 9000 (utilizado por PHP-FPM)
EXPOSE 9000

# Comando por defecto para iniciar PHP-FPM
CMD ["php-fpm"]
