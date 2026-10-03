FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

# بدون mariadb-server: الداتابيس الآن خارجية (TiDB Cloud)
RUN apt-get update && apt-get install -y \
    apache2 \
    php libapache2-mod-php php-mysql php-mbstring php-xml php-curl php-zip php-gd \
    python3 python3-pip python3-venv \
    curl \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

RUN PHP_INI=$(find /etc/php -name php.ini | grep apache2 | head -1) && \
    sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 64M/' $PHP_INI && \
    sed -i 's/post_max_size = 8M/post_max_size = 64M/' $PHP_INI && \
    sed -i 's/max_execution_time = 30/max_execution_time = 300/' $PHP_INI || true

# مكتبات بايثون أولاً (للاستفادة من الكاش)، بنسخ requirements.txt حتى يتوافق الموديل
COPY ai_model/requirements.txt /tmp/requirements.txt
RUN pip3 install --no-cache-dir -r /tmp/requirements.txt && \
    pip3 install --no-cache-dir flask flask-cors gunicorn

RUN rm -f /var/www/html/index.html

COPY . /var/www/html/

# ضمان أن start.sh بنهايات أسطر Linux (LF) حتى لو اتحفظ على Windows
RUN sed -i 's/\r$//' /var/www/html/start.sh && chmod +x /var/www/html/start.sh

RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

RUN touch /var/log/gunicorn.log /var/log/gunicorn.error.log && \
    chmod 666 /var/log/gunicorn.log /var/log/gunicorn.error.log

RUN cat > /etc/apache2/sites-available/000-default.conf <<'EOF'
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    DirectoryIndex index.php index.html
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

WORKDIR /var/www/html/ai_model

EXPOSE 80

CMD ["/var/www/html/start.sh"]