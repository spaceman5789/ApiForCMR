FROM php:8.2-fpm

# Mettre à jour et installer les dépendances requises
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    wget \
    cron

# Installer l'extension PHP zip
RUN docker-php-ext-install zip
RUN docker-php-ext-install pdo pdo_mysql
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Installer Symfony CLI
RUN wget https://get.symfony.com/cli/installer -O - | bash \
    && mv /root/.symfony5/bin/symfony /usr/local/bin/symfony

# Copier le fichier crontab dans le conteneur
COPY crontab.txt /etc/cron.d/myjob
RUN chmod 0644 /etc/cron.d/myjob

# Copier le script bash dans le conteneur
COPY ./trigger_api_call.sh /var/www/trigger_api_call.sh

# Nettoyer le script des séquences indésirables
RUN sed -i 's/\^\[\[200~//g' /var/www/trigger_api_call.sh

# Donner les permissions nécessaires
RUN chmod +x /var/www/trigger_api_call.sh

# Créer le répertoire de logs de cron
RUN touch /var/log/cron.log

# Appliquer la crontab
RUN crontab /etc/cron.d/myjob

# Commande pour lancer cron en arrière-plan et php-fpm
CMD cron && php-fpm
