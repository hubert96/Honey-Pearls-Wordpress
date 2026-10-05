FROM php:8.3-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev libicu-dev libonig-dev libxml2-dev libxslt1-dev && docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install -j2 gd intl zip mbstring mysqli pdo_mysql exif opcache bcmath xsl && a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/honey-pearls-wordpress
COPY . .
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader && mkdir -p web/app/uploads web/app/wflogs && chown -R www-data:www-data web/app/uploads web/app/wflogs
RUN sed -i 's!/var/www/html!/var/www/honey-pearls-wordpress/web!g' /etc/apache2/sites-available/000-default.conf && printf '<Directory /var/www/honey-pearls-wordpress/web>\nAllowOverride All\nFallbackResource /index.php\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/bedrock.conf && a2enconf bedrock
RUN printf 'memory_limit=256M\nupload_max_filesize=64M\npost_max_size=64M\nmax_execution_time=120\nopcache.enable=1\n' > /usr/local/etc/php/conf.d/bedrock.ini && printf 'ok\n' > web/healthz
RUN printf '<IfModule mpm_prefork_module>\nStartServers 1\nMinSpareServers 1\nMaxSpareServers 2\nMaxRequestWorkers 5\nMaxConnectionsPerChild 500\n</IfModule>\n' > /etc/apache2/mods-available/mpm_prefork.conf
EXPOSE 80
