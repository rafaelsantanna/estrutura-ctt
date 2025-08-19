@echo off
echo ===================================
echo Address CTT Laravel - Docker Setup
echo ===================================

REM Copy environment file
if not exist .env (
    echo Creating .env file...
    copy .env.example .env
)

REM Build and start containers
echo Building Docker containers...
docker-compose build

echo Starting containers...
docker-compose up -d

REM Wait for MySQL to be ready
echo Waiting for MySQL to be ready...
timeout /t 15 /nobreak >nul

REM Install Laravel if needed
echo Setting up Laravel...
docker-compose exec app bash -c "if [ ! -f 'vendor/autoload.php' ]; then composer create-project --prefer-dist laravel/laravel temp-laravel '10.*' && cp -n temp-laravel/* . 2>/dev/null || : && cp -r temp-laravel/app/* app/ 2>/dev/null || : && cp -r temp-laravel/bootstrap . 2>/dev/null || : && cp -r temp-laravel/config . 2>/dev/null || : && cp -r temp-laravel/public . 2>/dev/null || : && cp -r temp-laravel/resources . 2>/dev/null || : && cp -r temp-laravel/routes . 2>/dev/null || : && cp -r temp-laravel/storage . 2>/dev/null || : && rm -rf temp-laravel; fi && composer install && php artisan key:generate"

REM Run migrations
echo Running migrations...
docker-compose exec app php artisan migrate --force

REM Import CTT data
echo Importing CTT data (this may take 5-15 minutes)...
docker-compose exec app php artisan import:ctt-data --force

REM Set permissions
echo Setting permissions...
docker-compose exec app bash -c "chown -R www-data:www-data /var/www && chmod -R 775 /var/www/storage && chmod -R 775 /var/www/bootstrap/cache"

echo.
echo ===================================
echo Setup Complete!
echo ===================================
echo.
echo Access points:
echo - Laravel Application: http://localhost:8080
echo - phpMyAdmin: http://localhost:8081
echo - MySQL: localhost:3307 (user: laravel, password: secret)
echo.
echo Useful commands:
echo - Stop containers: docker-compose down
echo - View logs: docker-compose logs -f app
echo - Execute commands: docker-compose exec app php artisan [command]
echo - Re-import data: docker-compose exec app php artisan import:ctt-data --force
echo.
pause