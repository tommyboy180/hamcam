# ============================================================
# HamCAM Dockerfile
# Version: 1.1
# ============================================================
# ---------------------------------------------
#  HamCAM Docker Image
#  PHP 8.2 + Apache
# ---------------------------------------------
FROM php:8.2-apache

# Enable required modules
RUN a2enmod rewrite proxy proxy_http proxy_wstunnel

# Set timezone
ENV TZ=UTC
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Security hardening
RUN echo "expose_php = Off" >> /usr/local/etc/php/php.ini && \
    echo "session.cookie_httponly = 1" >> /usr/local/etc/php/php.ini && \
    echo "session.cookie_samesite = Strict" >> /usr/local/etc/php/php.ini && \
    echo "session.use_strict_mode = 1" >> /usr/local/etc/php/php.ini

# Write the vhost config directly
RUN cat > /etc/apache2/sites-enabled/000-default.conf << 'VHEOF'
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html

    RewriteEngine On

    # MANDATORY: Handle WebSocket upgrades for go2rtc video
    # This must come BEFORE standard ProxyPass
    RewriteCond %{HTTP:Upgrade} websocket [NC]
    RewriteCond %{HTTP:Connection} upgrade [NC]
    RewriteRule ^/go2rtc/(.*)$ ws://go2rtc:1984/$1 [P,L]

    # Handle standard HTTP API calls (PTZ, etc)
    ProxyPass /go2rtc/ http://go2rtc:1984/
    ProxyPassReverse /go2rtc/ http://go2rtc:1984/

    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
VHEOF

# Copy application files
COPY . /var/www/html/

# Fix permissions
# config.php / go2rtc.yaml / .env are bind-mounted at runtime (see docker-compose.yml)
# so the setup wizard can write to them — what matters is the *host* file
# permissions (see init.sh), not these. Kept tolerant in case they're absent
# at build time.
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    (chmod 666 /var/www/html/config.php 2>/dev/null || true)

# Entrypoint script to fix /data permissions at runtime
# (volume is mounted after build, so we can't chmod at build time)
RUN echo '#!/bin/sh\nchown -R www-data:www-data /data 2>/dev/null || true\nchmod -R 775 /data 2>/dev/null || true\nexec apache2-foreground "$@"' > /usr/local/bin/hamcam-entrypoint.sh && \
    chmod +x /usr/local/bin/hamcam-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/hamcam-entrypoint.sh"]
EXPOSE 80