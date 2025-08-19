<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()
    ]);
});

// Get all districts
Route::get('/distritos', function () {
    $distritos = DB::table('distritos')->orderBy('nome')->get();
    return response()->json($distritos);
});

// Get municipalities by district
Route::get('/concelhos/{codigo_distrito}', function ($codigo_distrito) {
    $concelhos = DB::table('concelhos')
        ->where('codigo_distrito', $codigo_distrito)
        ->orderBy('nome')
        ->get();
    return response()->json($concelhos);
});

// Get parishes by district and municipality
Route::get('/freguesias/{codigo_distrito}/{codigo_concelho}', function ($codigo_distrito, $codigo_concelho) {
    $freguesias = DB::table('freguesias')
        ->where('codigo_distrito', $codigo_distrito)
        ->where('codigo_concelho', $codigo_concelho)
        ->orderBy('nome')
        ->get();
    return response()->json($freguesias);
});

// Get localities by district and municipality
Route::get('/localidades/{codigo_distrito}/{codigo_concelho}', function ($codigo_distrito, $codigo_concelho) {
    $localidades = DB::table('localidades')
        ->where('codigo_distrito', $codigo_distrito)
        ->where('codigo_concelho', $codigo_concelho)
        ->orderBy('nome')
        ->get();
    return response()->json($localidades);
});

// Search postal codes (GeoAPI style)
Route::get('/codigos-postais/search', function (Request $request) {
    $query = DB::table('codigos_postais as cp')
        ->select([
            'cp.*',
            'd.nome as distrito_nome',
            'c.nome as concelho_nome',
            'l.nome as localidade_nome',
            'f.nome as freguesia_nome'
        ])
        ->join('distritos as d', 'cp.codigo_distrito', '=', 'd.codigo')
        ->leftJoin('concelhos as c', function($join) {
            $join->on('cp.codigo_distrito', '=', 'c.codigo_distrito')
                 ->on('cp.codigo_concelho', '=', 'c.codigo_concelho');
        })
        ->leftJoin('localidades as l', 'cp.localidade_id', '=', 'l.id')
        ->leftJoin('freguesias as f', 'cp.freguesia_id', '=', 'f.id');
    
    // Filter by postal code
    if ($request->has('cp')) {
        $cp = str_replace('-', '', $request->cp);
        if (strlen($cp) >= 4) {
            $cp4 = substr($cp, 0, 4);
            $query->where('cp.cp4', $cp4);
            
            if (strlen($cp) >= 7) {
                $cp3 = substr($cp, 4, 3);
                $query->where('cp.cp3', $cp3);
            }
        }
    }
    
    // Filter by designation
    if ($request->has('designacao')) {
        $query->where('cp.designacao_postal', 'like', '%' . $request->designacao . '%');
    }
    
    // Filter by district
    if ($request->has('distrito')) {
        $query->where('cp.codigo_distrito', $request->distrito);
    }
    
    // Filter by municipality
    if ($request->has('concelho')) {
        $query->where('cp.codigo_concelho', $request->concelho);
    }
    
    $results = $query->limit(100)->get();
    
    return response()->json([
        'total' => $results->count(),
        'results' => $results
    ]);
});

// Get postal code by exact match
Route::get('/codigos-postais/{cp4}/{cp3}', function ($cp4, $cp3) {
    $codigo = DB::table('codigos_postais as cp')
        ->select([
            'cp.*',
            'd.nome as distrito_nome',
            'c.nome as concelho_nome',
            'l.nome as localidade_nome',
            'f.nome as freguesia_nome'
        ])
        ->join('distritos as d', 'cp.codigo_distrito', '=', 'd.codigo')
        ->leftJoin('concelhos as c', function($join) {
            $join->on('cp.codigo_distrito', '=', 'c.codigo_distrito')
                 ->on('cp.codigo_concelho', '=', 'c.codigo_concelho');
        })
        ->leftJoin('localidades as l', 'cp.localidade_id', '=', 'l.id')
        ->leftJoin('freguesias as f', 'cp.freguesia_id', '=', 'f.id')
        ->where('cp.cp4', $cp4)
        ->where('cp.cp3', $cp3)
        ->first();
    
    if (!$codigo) {
        return response()->json(['error' => 'Postal code not found'], 404);
    }
    
    return response()->json($codigo);
});

// Statistics
Route::get('/stats', function () {
    $stats = [
        'distritos' => DB::table('distritos')->count(),
        'concelhos' => DB::table('concelhos')->count(),
        'freguesias' => DB::table('freguesias')->count(),
        'localidades' => DB::table('localidades')->count(),
        'codigos_postais' => DB::table('codigos_postais')->count(),
    ];
    
    return response()->json($stats);
});
