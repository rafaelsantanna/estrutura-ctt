@echo off
echo ===================================
echo Testing Address CTT API
echo ===================================
echo.

REM Test if application is running
curl -s http://localhost:8080 >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Application is not accessible at http://localhost:8080
    echo Please ensure Docker containers are running.
    pause
    exit /b 1
)

echo Creating test route file...
docker-compose exec -T app bash -c "cat > routes/api.php << 'EOF'
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/test', function () {
    return response()->json([
        'status' => 'API is working',
        'timestamp' => now()
    ]);
});

Route::get('/stats', function () {
    return response()->json([
        'distritos' => DB::table('distritos')->count(),
        'concelhos' => DB::table('concelhos')->count(),
        'freguesias' => DB::table('freguesias')->count(),
        'localidades' => DB::table('localidades')->count(),
        'codigos_postais' => DB::table('codigos_postais')->count()
    ]);
});

Route::get('/postal-code/{cp4}/{cp3}', function (\$cp4, \$cp3) {
    \$result = DB::table('codigos_postais')
        ->where('cp4', \$cp4)
        ->where('cp3', \$cp3)
        ->first();
    
    if (!\$result) {
        return response()->json(['error' => 'Postal code not found'], 404);
    }
    
    return response()->json(\$result);
});

Route::get('/search/locality', function (Request \$request) {
    \$query = \$request->get('q', '');
    
    if (strlen(\$query) < 3) {
        return response()->json(['error' => 'Query must be at least 3 characters'], 400);
    }
    
    \$results = DB::table('localidades')
        ->where('nome', 'like', \$query . '%%')
        ->limit(10)
        ->get();
    
    return response()->json(\$results);
});

Route::get('/districts', function () {
    return response()->json(
        DB::table('distritos')->orderBy('nome')->get()
    );
});

Route::get('/district/{code}/municipalities', function (\$code) {
    return response()->json(
        DB::table('concelhos')
            ->where('codigo_distrito', \$code)
            ->orderBy('nome')
            ->get()
    );
});
EOF"

echo.
echo Testing API endpoints...
echo.

echo 1. Testing API status:
curl -s http://localhost:8080/api/test
echo.
echo.

echo 2. Getting statistics:
curl -s http://localhost:8080/api/stats
echo.
echo.

echo 3. Testing postal code 1000-001 (Lisboa):
curl -s http://localhost:8080/api/postal-code/1000/001
echo.
echo.

echo 4. Searching for localities starting with 'Lisboa':
curl -s "http://localhost:8080/api/search/locality?q=Lisboa"
echo.
echo.

echo 5. Listing all districts:
curl -s http://localhost:8080/api/districts
echo.
echo.

echo ===================================
echo Test complete!
echo ===================================
pause