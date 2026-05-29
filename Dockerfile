FROM php:8.2-apache

# Permite que o Apache use a porta que o Render mandar
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf
RUN sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/g' /etc/apache2/sites-available/000-default.conf

# Copia todos os seus arquivos PHP para a pasta do servidor web
COPY . /var/www/html/

# Ajusta as permissões para não dar erro
RUN chown -r www-data:www-data /var/www/html/

EXPOSE 80
