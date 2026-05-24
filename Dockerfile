FROM php:8.2-cli
RUN docker-php-ext-install mysqli
COPY . /app
WORKDIR /app
CMD sh -c "php -S 0.0.0.0:${PORT:-8080} -t /app"
