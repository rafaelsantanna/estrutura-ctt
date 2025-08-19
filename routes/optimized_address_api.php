<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OptimizedAddressController;

/**
 * API OTIMIZADA PARA FORMULÁRIOS DE ENDEREÇO
 * Estrutura simplificada sem JOINs complexos
 * Cache inteligente para performance máxima
 */

// ===== SELEÇÃO HIERÁRQUICA =====

// Lista todos os distritos
Route::get('/distritos', [OptimizedAddressController::class, 'getDistritos'])
    ->name('api.distritos');

// Lista concelhos de um distrito
Route::get('/concelhos/{codigoDistrito}', [OptimizedAddressController::class, 'getConcelhos'])
    ->where('codigoDistrito', '[0-9]{2}')
    ->name('api.concelhos');

// Lista localidades de um concelho
Route::get('/localidades/{codigoDistrito}/{codigoConcelho}', [OptimizedAddressController::class, 'getLocalidades'])
    ->where(['codigoDistrito' => '[0-9]{2}', 'codigoConcelho' => '[0-9]{2}'])
    ->name('api.localidades');

// ===== BUSCA POR CÓDIGO POSTAL =====

// Busca endereço por código postal completo
Route::get('/codigo-postal/{cp4}/{cp3}', [OptimizedAddressController::class, 'getCodigoPostal'])
    ->where(['cp4' => '[0-9]{4}', 'cp3' => '[0-9]{3}'])
    ->name('api.codigo-postal');

// Valida código postal (aceita formato 0000-000 ou 0000000)
Route::get('/validate/cp/{codigoPostal}', [OptimizedAddressController::class, 'validateCodigoPostal'])
    ->where('codigoPostal', '[0-9]{4}-?[0-9]{3}')
    ->name('api.validate-cp');

// ===== AUTOCOMPLETE =====

// Autocomplete de códigos postais
// GET /api/autocomplete/cp?q=3750
Route::get('/autocomplete/cp', [OptimizedAddressController::class, 'autocompleteCodigoPostal'])
    ->name('api.autocomplete.cp');

// Autocomplete de localidades
// GET /api/autocomplete/localidades?q=lisboa
Route::get('/autocomplete/localidades', [OptimizedAddressController::class, 'autocompleteLocalidades'])
    ->name('api.autocomplete.localidades');

// ===== BUSCA TEXTUAL =====

// Busca textual avançada (full-text search)
// GET /api/search?q=rua+das+flores+lisboa
Route::get('/search', [OptimizedAddressController::class, 'searchEnderecos'])
    ->name('api.search.enderecos');

// ===== UTILITÁRIOS =====

// Estatísticas da base de dados
Route::get('/stats', [OptimizedAddressController::class, 'getStats'])
    ->name('api.stats');

/*
=== EXEMPLOS DE USO ===

1. FORMULÁRIO HIERARQUICO:
   GET /api/distritos
   GET /api/concelhos/11  (Lisboa)
   GET /api/localidades/11/06  (Cascais)

2. PREENCHIMENTO AUTOMÁTICO POR CP:
   GET /api/codigo-postal/3750/011
   GET /api/validate/cp/3750-011

3. AUTOCOMPLETE:
   GET /api/autocomplete/cp?q=3750
   GET /api/autocomplete/localidades?q=casca

4. BUSCA TEXTUAL:
   GET /api/search?q=rua+central+porto

=== PERFORMANCE ESPERADA ===

- Distritos: ~5ms (cache)
- Concelhos: ~10ms (cache)
- Localidades: ~15ms (cache)
- Código postal: ~20ms (cache)
- Autocomplete: ~30ms
- Busca textual: ~50ms

Todos os endpoints usam cache inteligente para máxima performance.
*/