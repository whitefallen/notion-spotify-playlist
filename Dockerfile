FROM php:8.1-cli

# Install necessary dependencies
RUN apt-get update && apt-get install -y \
    cron \
    libzip-dev \
    && docker-php-ext-install zip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www/html

# Copy your application files into the container
COPY . /var/www/html

# Install Composer dependencies
RUN composer install

# Install Symfony CLI (optional, if you need to run Symfony commands manually)
RUN curl -sS https://get.symfony.com/cli/installer | bash
RUN mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

# Add the CRON job to run the Symfony command periodically
RUN echo "0 1 1 * * /usr/local/bin/php /var/www/html/bin/console spotify:update-playlist >> /var/log/cron.log 2>&1" > /etc/cron.d/update-playlist

# Set proper permissions for the cron job
RUN chmod 0644 /etc/cron.d/update-playlist

# Apply the cron job
RUN crontab /etc/cron.d/update-playlist

# Start cron and Symfony
ENTRYPOINT ["sh", "-c", "service cron && php -S 0.0.0.0:3490 -t public"]
