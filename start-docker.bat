@echo off
echo ===================================
echo Address CTT Laravel - Setup Docker
echo ===================================
echo.

REM Check if Docker Desktop is running
docker version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Docker Desktop is not running!
    echo.
    echo Please start Docker Desktop and try again.
    echo Opening Docker Desktop...
    start "" "C:\Program Files\Docker\Docker\Docker Desktop.exe"
    echo.
    echo Waiting for Docker to start (30 seconds)...
    timeout /t 30 /nobreak
    echo.
    echo After Docker Desktop starts, run this script again.
    pause
    exit /b 1
)

echo Docker is running!
echo.

REM Copy environment file
if not exist .env (
    echo Creating .env file...
    copy .env.example .env
)

REM Build and start containers
echo Building Docker containers (this may take several minutes)...
docker-compose build

if %errorlevel% neq 0 (
    echo Error building containers!
    pause
    exit /b 1
)

echo.
echo Starting containers...
docker-compose up -d

if %errorlevel% neq 0 (
    echo Error starting containers!
    pause
    exit /b 1
)

echo.
echo Waiting for MySQL to initialize (20 seconds)...
timeout /t 20 /nobreak >nul

echo.
echo ===================================
echo Setup Complete!
echo ===================================
echo.
echo The containers are running!
echo.
echo Next steps:
echo 1. Run: setup-laravel.bat to install Laravel and import data
echo 2. Access the application at: http://localhost:8080
echo 3. Access phpMyAdmin at: http://localhost:8081
echo.
pause