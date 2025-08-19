@echo off
echo ===================================
echo Laravel Setup and Data Import
echo ===================================
echo.

REM Check if containers are running
docker-compose ps | findstr "app" >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Docker containers are not running!
    echo Please run start-docker.bat first.
    pause
    exit /b 1
)

echo Installing Laravel framework...
docker-compose exec -T app bash -c "if [ ! -d 'vendor' ]; then composer create-project --prefer-dist laravel/laravel temp '10.*' --no-interaction && cp -n temp/* . 2>/dev/null || : && cp -r temp/app/* app/ 2>/dev/null || : && cp -r temp/bootstrap . 2>/dev/null || : && cp -r temp/config . 2>/dev/null || : && cp -r temp/public . 2>/dev/null || : && cp -r temp/resources . 2>/dev/null || : && cp -r temp/routes . 2>/dev/null || : && cp -r temp/storage . 2>/dev/null || : && cp -r temp/tests . 2>/dev/null || : && rm -rf temp; fi"

echo.
echo Installing Composer dependencies...
docker-compose exec -T app composer install --no-interaction

echo.
echo Generating application key...
docker-compose exec -T app php artisan key:generate

echo.
echo Running database migrations...
docker-compose exec -T app php artisan migrate --force

echo.
echo Importing CTT postal code data...
echo This will import ~326,000 postal codes and may take 5-15 minutes...
docker-compose exec -T app php artisan import:ctt-data --force

echo.
echo Setting correct permissions...
docker-compose exec -T app bash -c "chown -R www-data:www-data /var/www && chmod -R 775 storage bootstrap/cache"

echo.
echo ===================================
echo Setup Complete!
echo ===================================
echo.
echo Application is ready at: http://localhost:8080
echo phpMyAdmin is available at: http://localhost:8081
echo   Username: laravel
echo   Password: secret
echo.
echo Database contains:
echo - 29 districts (distritos)
echo - 308 municipalities (concelhos)  
echo - ~4,000 parishes (freguesias)
echo - ~50,000 localities (localidades)
echo - ~326,000 postal codes (codigos_postais)
echo.
pause