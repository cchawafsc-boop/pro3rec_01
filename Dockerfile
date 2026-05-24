FROM php:8.2-apache
RUN docker-php-ext-install mysqli \
    && a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork
COPY . /var/www/html/
EXPOSE 80
CMD ["apache2ctl", "-D", "FOREGROUND"]
