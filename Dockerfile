FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql
COPY . /var/www/html/

# Oculta versiones y bloquea el acceso web a código interno, repositorio y SQL
RUN a2enmod headers \
 && printf 'ServerTokens Prod\nServerSignature Off\nTraceEnable Off\n' > /etc/apache2/conf-available/seguridad.conf \
 && printf '<DirectoryMatch "/\\.git">\n    Require all denied\n</DirectoryMatch>\n<Directory /var/www/html/util>\n    Require all denied\n</Directory>\n<FilesMatch "\\.(sql|cs|md|pem|env|ini|log|bak)$">\n    Require all denied\n</FilesMatch>\nOptions -Indexes\n' >> /etc/apache2/conf-available/seguridad.conf \
 && a2enconf seguridad \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf 'expose_php=Off\ndisplay_errors=Off\nlog_errors=On\nsession.use_strict_mode=1\nsession.cookie_httponly=1\n' > "$PHP_INI_DIR/conf.d/zz-seguridad.ini"
EXPOSE 80