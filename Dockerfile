FROM php:8.2-apache
RUN apt-get update \
 && apt-get install -y --no-install-recommends libonig-dev \
 && docker-php-ext-install pdo_mysql mbstring \
 && rm -rf /var/lib/apt/lists/*
COPY . /var/www/html/

# Oculta versiones y bloquea el acceso web a código interno, repositorio y SQL
RUN a2enmod headers rewrite \
 && printf 'ServerTokens Prod\nServerSignature Off\nTraceEnable Off\n' > /etc/apache2/conf-available/seguridad.conf \
 && printf '<DirectoryMatch "/(?:\\.git|util|tests)(?:/|$)">\n    Require all denied\n</DirectoryMatch>\n<FilesMatch "(?i)(?:\\.(sql|cs|md|pem|crt|key|env|ini|log|bak|ps1|txt)$|^(?:menuLateral|guia)\\.php$)">\n    Require all denied\n</FilesMatch>\nOptions -Indexes\n' >> /etc/apache2/conf-available/seguridad.conf \
 && printf '<Directory /var/www/html>\n    Options +FollowSymLinks -MultiViews\n    AllowOverrideList RewriteEngine RewriteRule\n</Directory>\n' >> /etc/apache2/conf-available/seguridad.conf \
 && a2enconf seguridad \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf 'expose_php=Off\ndisplay_errors=Off\nlog_errors=On\nsession.use_strict_mode=1\nsession.cookie_httponly=1\n' > "$PHP_INI_DIR/conf.d/zz-seguridad.ini"
EXPOSE 80
CMD ["apache2-foreground"]