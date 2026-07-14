@echo off
REM Start Dugsi ERP local server (PHP must have pdo_mysql enabled)
php artisan serve --host=127.0.0.1 --port=8000
