<?php

namespace App\Http\Controllers;

use Illuminate\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Controller otimizado para formulários de endereço
 * Usa a estrutura simplificada sem JOINs complexos
 */
class OptimizedAddressController extends Controller
{
    /**
     * Lista todos os distritos
     * GET /api/distritos
     */
    public function getDistritos()
    {
        return Cache::remember('distritos', 3600, function () {
            return DB::table('distritos_opt')
                ->select('codigo', 'nome')
                ->orderBy('nome')
                ->get();
        });
    }
    
    /**
     * Lista concelhos de um distrito
     * GET /api/concelhos/{codigoDistrito}
     */
    public function getConcelhos($codigoDistrito)
    {
        return Cache::remember("concelhos_{$codigoDistrito}", 3600, function () use ($codigoDistrito) {
            return DB::table('concelhos_opt')
                ->select('id', 'codigo_concelho', 'nome')
                ->where('codigo_distrito', $codigoDistrito)
                ->orderBy('nome')
                ->get();
        });
    }
    
    /**
     * Lista localidades de um concelho
     * GET /api/localidades/{codigoDistrito}/{codigoConcelho}
     */
    public function getLocalidades($codigoDistrito, $codigoConcelho)
    {
        return Cache::remember("localidades_{$codigoDistrito}_{$codigoConcelho}", 3600, function () use ($codigoDistrito, $codigoConcelho) {
            return DB::table('localidades_opt')
                ->select('id', 'codigo_localidade', 'nome')
                ->where('codigo_distrito', $codigoDistrito)
                ->where('codigo_concelho', $codigoConcelho)
                ->orderBy('nome')
                ->get();
        });
    }
    
    /**
     * Busca por código postal (SEM JOINs!)
     * GET /api/codigo-postal/{cp4}/{cp3}
     * GET /api/codigo-postal/3750/011
     */
    public function getCodigoPostal($cp4, $cp3)
    {
        return Cache::remember("cp_{$cp4}_{$cp3}", 3600, function () use ($cp4, $cp3) {
            return DB::table('codigos_postais_opt')
                ->select([
                    'cp4',
                    'cp3',
                    DB::raw('CONCAT(cp4, "-", cp3) as codigo_postal'),
                    'designacao_postal',
                    'nome_distrito',
                    'nome_concelho',
                    'nome_localidade',
                    'tipo_via',
                    'nome_via'
                ])
                ->where('cp4', $cp4)
                ->where('cp3', $cp3)
                ->first();
        });
    }
    
    /**
     * Autocomplete de códigos postais
     * GET /api/autocomplete/cp?q=3750
     */
    public function autocompleteCodigoPostal(Request $request)
    {
        $query = $request->get('q');
        
        if (strlen($query) < 2) {
            return response()->json([]);
        }
        
        return Cache::remember("autocomplete_cp_{$query}", 300, function () use ($query) {
            return DB::table('codigos_postais_opt')
                ->select([
                    'cp4',
                    'cp3',
                    DB::raw('CONCAT(cp4, "-", cp3) as codigo_postal'),
                    'designacao_postal',
                    'nome_localidade'
                ])
                ->where('cp4', 'LIKE', $query . '%')
                ->distinct()
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'codigo_postal' => $item->codigo_postal,
                        'label' => "{$item->codigo_postal} - {$item->designacao_postal}",
                        'designacao' => $item->designacao_postal,
                        'localidade' => $item->nome_localidade
                    ];
                });
        });
    }
    
    /**
     * Autocomplete de localidades
     * GET /api/autocomplete/localidades?q=lisboa
     */
    public function autocompleteLocalidades(Request $request)
    {
        $query = $request->get('q');
        
        if (strlen($query) < 3) {
            return response()->json([]);
        }
        
        return Cache::remember("autocomplete_loc_{$query}", 300, function () use ($query) {
            return DB::table('localidades_opt')
                ->select('id', 'nome', 'codigo_distrito', 'codigo_concelho')
                ->where('nome', 'LIKE', '%' . $query . '%')
                ->limit(15)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'nome' => $item->nome,
                        'codigo_completo' => "{$item->codigo_distrito}.{$item->codigo_concelho}"
                    ];
                });
        });
    }
    
    /**
     * Busca textual avançada (full-text search)
     * GET /api/search?q=rua+das+flores+lisboa
     */
    public function searchEnderecos(Request $request)
    {
        $query = $request->get('q');
        
        if (strlen($query) < 4) {
            return response()->json([]);
        }
        
        return Cache::remember("search_{$query}", 300, function () use ($query) {
            return DB::table('codigos_postais_opt')
                ->select([
                    DB::raw('CONCAT(cp4, "-", cp3) as codigo_postal'),
                    'designacao_postal',
                    'nome_distrito',
                    'nome_concelho',
                    'nome_localidade',
                    'tipo_via',
                    'nome_via'
                ])
                ->whereRaw('MATCH(designacao_postal, nome_via) AGAINST(? IN BOOLEAN MODE)', [$query])
                ->limit(20)
                ->get()
                ->map(function ($item) {
                    $endereco = [];
                    
                    if ($item->tipo_via && $item->nome_via) {
                        $endereco[] = "{$item->tipo_via} {$item->nome_via}";
                    }
                    
                    $endereco[] = $item->designacao_postal;
                    $endereco[] = "{$item->nome_localidade}, {$item->nome_concelho}";
                    $endereco[] = $item->nome_distrito;
                    
                    return [
                        'codigo_postal' => $item->codigo_postal,
                        'endereco_completo' => implode(', ', array_filter($endereco)),
                        'distrito' => $item->nome_distrito,
                        'concelho' => $item->nome_concelho,
                        'localidade' => $item->nome_localidade
                    ];
                });
        });
    }
    
    /**
     * Valida código postal
     * GET /api/validate/cp/{codigoPostal}
     * GET /api/validate/cp/3750-011
     */
    public function validateCodigoPostal($codigoPostal)
    {
        // Extrair CP4 e CP3
        if (preg_match('/^(\d{4})-?(\d{3})$/', $codigoPostal, $matches)) {
            $cp4 = $matches[1];
            $cp3 = $matches[2];
            
            $exists = DB::table('codigos_postais_opt')
                ->where('cp4', $cp4)
                ->where('cp3', $cp3)
                ->exists();
            
            return response()->json([
                'valid' => $exists,
                'codigo_postal' => "{$cp4}-{$cp3}",
                'formatted' => $exists ? $this->getCodigoPostal($cp4, $cp3) : null
            ]);
        }
        
        return response()->json([
            'valid' => false,
            'error' => 'Formato inválido. Use: 0000-000'
        ]);
    }
    
    /**
     * Estatísticas da base de dados
     * GET /api/stats
     */
    public function getStats()
    {
        return Cache::remember('address_stats', 1800, function () {
            return [
                'distritos' => DB::table('distritos_opt')->count(),
                'concelhos' => DB::table('concelhos_opt')->count(),
                'localidades' => DB::table('localidades_opt')->count(),
                'codigos_postais' => DB::table('codigos_postais_opt')->count(),
                'last_update' => DB::table('codigos_postais_opt')->max('updated_at'),
                'database_size' => $this->getDatabaseSize()
            ];
        });
    }
    
    private function getDatabaseSize()
    {
        $size = DB::select("
            SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
            AND table_name LIKE '%_opt'
        ")[0]->size_mb ?? 0;
        
        return "{$size} MB";
    }
}