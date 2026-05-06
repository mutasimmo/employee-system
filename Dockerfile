# استخدام صورة PHP رسمية مع Apache
FROM php:8.2-apache

# تثبيت الملحقات المطلوبة لـ PostgreSQL و ZIP و GD
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_pgsql pgsql zip

# تمكين mod_rewrite في Apache
RUN a2enmod rewrite

# نسخ جميع ملفات المشروع إلى مجلد Apache
COPY . /var/www/html/

# تعيين الصلاحيات المناسبة
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# تعيين مجلد العمل
WORKDIR /var/www/html

# فتح المنفذ 80 (Apache)
EXPOSE 80

# تشغيل Apache
CMD ["apache2-foreground"]