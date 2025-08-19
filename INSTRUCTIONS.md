# Address CTT Laravel - Docker Setup Instructions

## Prerequisites
- Docker Desktop for Windows installed and running
- At least 4GB RAM available for Docker
- Port 8080, 8081, and 3307 available

## Quick Start

### Step 1: Start Docker Desktop
Make sure Docker Desktop is running on your Windows machine.

### Step 2: Run Setup Scripts
Execute the following batch files in order:

1. **start-docker.bat** - Starts Docker containers
   - Checks if Docker is running
   - Builds the Docker images
   - Starts all containers (Laravel, MySQL, phpMyAdmin)

2. **setup-laravel.bat** - Installs Laravel and imports data
   - Installs Laravel 10 framework
   - Runs database migrations
   - Imports 326,000+ Portuguese postal codes
   - Sets up proper permissions

3. **test-api.bat** - Tests the API endpoints (optional)
   - Creates test routes
   - Validates data import
   - Shows sample API responses

## Access Points

After setup is complete, you can access:

- **Laravel Application**: http://localhost:8080
- **phpMyAdmin**: http://localhost:8081
  - Username: `laravel`
  - Password: `secret`
- **MySQL Database**: localhost:3307
  - Database: `address_ctt`
  - Username: `laravel`
  - Password: `secret`

## Database Structure

The system creates 5 hierarchical tables:

1. **distritos** - 29 Portuguese districts
2. **concelhos** - 308 municipalities 
3. **freguesias** - ~4,000 parishes
4. **localidades** - ~50,000 localities
5. **codigos_postais** - ~326,000 postal codes

## Using the Import Command

Inside the container, you can re-import data anytime:

```bash
docker-compose exec app php artisan import:ctt-data --force
```

Options available:
- `--force` - Delete existing data before import
- `--batch-size=5000` - Number of records per batch
- `--memory-limit=256` - Memory limit in MB
- `--path=/custom/path` - Custom data file path

## Sample API Endpoints

After running `test-api.bat`, these endpoints are available:

- `GET /api/stats` - Database statistics
- `GET /api/postal-code/{cp4}/{cp3}` - Get specific postal code
- `GET /api/search/locality?q=Lisboa` - Search localities
- `GET /api/districts` - List all districts
- `GET /api/district/{code}/municipalities` - List municipalities by district

## Example Usage in Laravel

```php
// Find postal code
$address = DB::table('codigos_postais')
    ->where('cp4', '1000')
    ->where('cp3', '001')
    ->first();

// Search localities
$localities = DB::table('localidades')
    ->where('nome', 'like', 'Lisboa%')
    ->limit(10)
    ->get();

// Get municipalities in Lisbon district
$municipalities = DB::table('concelhos')
    ->where('codigo_distrito', '11')
    ->get();
```

## Troubleshooting

### Docker not running
- Make sure Docker Desktop is started
- Run `start-docker.bat` which will attempt to start Docker Desktop

### Port already in use
- Change ports in `docker-compose.yml`:
  - `8080:80` → `8090:80` (for Laravel)
  - `8081:80` → `8091:80` (for phpMyAdmin)
  - `3307:3306` → `3308:3306` (for MySQL)

### Import takes too long
- Normal import time: 5-15 minutes for 326,000 records
- You can monitor progress in the console
- Use `--batch-size=10000` for faster import (uses more memory)

### Memory issues during import
- Increase memory limit: `--memory-limit=512`
- Reduce batch size: `--batch-size=1000`

## Stopping the Application

To stop all containers:
```bash
stop-docker.bat
```

Or manually:
```bash
docker-compose down
```

## Additional Commands

View logs:
```bash
docker-compose logs -f app
```

Enter container shell:
```bash
docker-compose exec app bash
```

Clear Laravel cache:
```bash
docker-compose exec app php artisan cache:clear
```

## Performance

The system is optimized for:
- Fast postal code lookups (indexed)
- Efficient locality searches (indexed)
- Hierarchical queries (foreign keys)
- Large dataset handling (batch processing)

Import performance:
- ~326,000 postal codes
- ~50,000 localities
- Processing speed: ~400-800 records/second
- Memory usage: Max 256MB (configurable)


  # 1. Copie os arquivos
  database/migrations/2025_01_19_200000_create_final_address_structure.php
  app/Console/Commands/ImportCttData.php
  storage/app/ctt/distritos.txt
  storage/app/ctt/concelhos.txt
  storage/app/ctt/todos_cp.txt

  # 2. Execute
  php artisan migrate
  php artisan import:ctt-data --force