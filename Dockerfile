FROM php:8.2-apache
COPY . /var/www/html/
RUN mkdir -p /var/www/html/rooms && chmod 777 /var/www/html/rooms