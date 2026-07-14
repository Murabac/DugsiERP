@echo off
REM Dugsi ERP — run Artisan with MySQL extensions enabled (PHP 8.5 local quirk)
php -d extension=mysqli -d extension=pdo_mysql artisan %*
