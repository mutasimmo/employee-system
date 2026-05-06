FROM php:8.2-apache

# تثبيت ملحقات PHP المطلوبة لـ PhpSpreadsheet و PostgreSQL
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip pdo pdo_pgsql

# تمكين mod_rewrite في Apache
RUN a2enmod rewrite

# نسخ ملفات المشروع إلى مجلد Apache
COPY . /var/www/html/

# تعيين الصلاحيات الصحيحة
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# تعيين متغيرات البيئة من Render (اختياري، يمكنك استخدام Environment Variables في Render UI)
ENV DB_HOST=${DB_HOST}
ENV DB_PORT=${DB_PORT}
ENV DB_NAME=${DB_NAME}
ENV DB_USER=${DB_USER}
ENV DB_PASSWORD=${DB_PASSWORD}