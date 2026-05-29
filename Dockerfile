FROM php:8.2-apache

# Permite que o Apache use a porta que o Render mandar
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf
RUN sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/g' /etc/apache2/sites-available/000-default.conf

# Copia todos os seus arquivos PHP para a pasta do servidor web
COPY . /var/www/html/

# O AJUSTE AQUI: Mudado de -r para -R maiúsculo
RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80
