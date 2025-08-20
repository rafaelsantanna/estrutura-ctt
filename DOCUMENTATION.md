# Sistema de Importação de Dados CTT - Documentação Técnica Completa

## Índice
1. [Visão Geral](#visão-geral)
2. [Arquitetura do Banco de Dados](#arquitetura-do-banco-de-dados)
3. [Migration - Estrutura Detalhada](#migration---estrutura-detalhada)
4. [Comando de Importação](#comando-de-importação)
5. [Otimizações de Performance](#otimizações-de-performance)
6. [Segurança](#segurança)
7. [Métricas e Resultados](#métricas-e-resultados)
8. [Troubleshooting](#troubleshooting)

## Visão Geral

Sistema desenvolvido em Laravel para importação e gestão de dados postais dos CTT (Correios de Portugal). Processa e armazena ~236,000 endereços únicos com estrutura hierárquica completa.

### Características Principais
- **Volume de Dados**: 326,647 registros brutos → 236,348 endereços importados
- **Performance**: Importação completa em 3-5 minutos
- **Memória**: Otimizado de 2GB+ para ~256MB de uso máximo
- **Taxa de Sucesso**: 100% dos registros válidos importados

## Arquitetura do Banco de Dados

### Hierarquia de 5 Níveis

```
DISTRITOS (29 registros)
    ↓
CONCELHOS (298 registros)
    ↓
FREGUESIAS (2,851 registros) ←→ LOCALIDADES (23,516 registros)
                                        ↓
                              CÓDIGOS POSTAIS (236,348 registros)
```

### Relacionamentos
- **1:N Strict**: Distrito → Concelho → Freguesia
- **N:N Flexible**: Freguesias ↔ Localidades (via foreign keys opcionais)
- **1:N Multiple**: Um código postal pode ter múltiplos endereços

## Migration - Estrutura Detalhada

### Arquivo: `2025_01_19_200000_create_final_address_structure.php`

#### 1. Tabela DISTRITOS
```php
Schema::create('distritos', function (Blueprint $table) {
    $table->string('codigo', 2)->primary();  // Código distrito (01-29)
    $table->string('nome', 100);             // Nome do distrito
    $table->timestamps();
    
    $table->index('nome');  // Índice para buscas por nome
});
```

**Características Importantes:**
- Chave primária: `codigo` (string de 2 caracteres)
- Suporta códigos CTT oficiais (01-29)
- Índice em `nome` para queries de busca

#### 2. Tabela CONCELHOS
```php
Schema::create('concelhos', function (Blueprint $table) {
    $table->id();
    $table->string('codigo_distrito', 2);
    $table->string('codigo_concelho', 2);
    $table->string('nome', 100);
    $table->timestamps();
    
    // Foreign key com RESTRICT - impede exclusão de distrito com concelhos
    $table->foreign('codigo_distrito')
          ->references('codigo')
          ->on('distritos')
          ->onDelete('restrict');
    
    // Garante unicidade do par distrito+concelho
    $table->unique(['codigo_distrito', 'codigo_concelho']);
    $table->index('nome');
    $table->index('codigo_distrito');
});
```

**Pontos Críticos:**
- `onDelete('restrict')`: Proteção contra exclusão em cascata
- Unique constraint: Previne duplicação de códigos
- Múltiplos índices para otimização de queries

#### 3. Tabela FREGUESIAS
```php
Schema::create('freguesias', function (Blueprint $table) {
    $table->id();
    $table->string('codigo_distrito', 2);
    $table->string('codigo_concelho', 2);
    $table->string('codigo_freguesia', 6)->nullable();
    $table->string('nome', 150);
    $table->timestamps();
    
    // FK para distrito
    $table->foreign('codigo_distrito')
          ->references('codigo')
          ->on('distritos')
          ->onDelete('restrict');
    
    // FK composta para concelho
    $table->foreign(['codigo_distrito', 'codigo_concelho'])
          ->references(['codigo_distrito', 'codigo_concelho'])
          ->on('concelhos')
          ->onDelete('restrict');
    
    // Unique por nome dentro do concelho
    $table->unique(['codigo_distrito', 'codigo_concelho', 'nome']);
    $table->index('nome');
    $table->index(['codigo_distrito', 'codigo_concelho']);
});
```

**Design Decisions:**
- Foreign key composta para manter integridade referencial
- `codigo_freguesia` nullable para flexibilidade
- Nome pode ter até 150 caracteres (nomes compostos)

#### 4. Tabela LOCALIDADES
```php
Schema::create('localidades', function (Blueprint $table) {
    $table->id();
    $table->string('codigo_distrito', 2);
    $table->string('codigo_concelho', 2);
    $table->string('codigo_localidade', 10);
    $table->string('nome', 150);
    $table->unsignedBigInteger('freguesia_id')->nullable();
    $table->timestamps();
    
    // FKs com diferentes estratégias de delete
    $table->foreign('codigo_distrito')
          ->references('codigo')
          ->on('distritos')
          ->onDelete('restrict');
    
    $table->foreign(['codigo_distrito', 'codigo_concelho'])
          ->references(['codigo_distrito', 'codigo_concelho'])
          ->on('concelhos')
          ->onDelete('restrict');
    
    // SET NULL para relação opcional com freguesia
    $table->foreign('freguesia_id')
          ->references('id')
          ->on('freguesias')
          ->onDelete('set null');
    
    // Unique constraint com nome curto para MySQL
    $table->unique(
        ['codigo_distrito', 'codigo_concelho', 'codigo_localidade'], 
        'localidades_unique'
    );
    
    $table->index('nome');
    $table->index(['codigo_distrito', 'codigo_concelho']);
    $table->index('freguesia_id');
});
```

**Aspectos Técnicos:**
- `freguesia_id` nullable com SET NULL on delete
- Nome do índice unique encurtado (MySQL limit 64 chars)
- Múltiplos índices para JOIN optimization

#### 5. Tabela CÓDIGOS POSTAIS
```php
Schema::create('codigos_postais', function (Blueprint $table) {
    $table->id();
    $table->string('cp4', 4);        // Primeiros 4 dígitos do CP
    $table->string('cp3', 3);        // Últimos 3 dígitos do CP
    $table->string('codigo_distrito', 2);
    $table->string('codigo_concelho', 2);
    $table->string('codigo_localidade', 10);
    $table->unsignedBigInteger('localidade_id')->nullable();
    $table->unsignedBigInteger('freguesia_id')->nullable();
    $table->string('designacao_postal', 200);
    $table->string('morada', 500)->nullable();
    $table->boolean('is_primary')->default(false);  // Marca endereço principal
    $table->timestamps();
    
    // Foreign keys
    $table->foreign('codigo_distrito')
          ->references('codigo')
          ->on('distritos')
          ->onDelete('restrict');
    
    $table->foreign('localidade_id')
          ->references('id')
          ->on('localidades')
          ->onDelete('set null');
    
    $table->foreign('freguesia_id')
          ->references('id')
          ->on('freguesias')
          ->onDelete('set null');
    
    // Índices otimizados para busca tipo GeoAPI
    $table->index('cp4');
    $table->index(['cp4', 'cp3']);
    $table->index(['cp4', 'cp3', 'is_primary'], 'cp_primary_lookup');
    $table->index(['cp4', 'cp3', 'designacao_postal'], 'cp_designacao_lookup');
    $table->index(['cp4', 'cp3', 'designacao_postal', 'morada'], 'address_uniqueness_check');
    $table->index('designacao_postal');
    $table->index(['codigo_distrito', 'codigo_concelho']);
    $table->index('localidade_id');
    $table->index('freguesia_id');
});
```

**Decisões Críticas:**
- **SEM UNIQUE em ['cp4', 'cp3']**: Permite múltiplos endereços por CP
- `is_primary`: Identifica endereço principal de cada código postal
- `morada`: Campo grande (500 chars) para endereços completos
- 9 índices para otimizar diferentes tipos de busca

## Comando de Importação

### Arquivo: `app/Console/Commands/ImportCttData.php`

#### Estrutura Principal
```php
class ImportCttData extends Command
{
    protected $signature = 'import:ctt-data 
        {--force : Força reimportação}
        {--path= : Caminho alternativo}
        {--batch-size=5000 : Tamanho do batch}
        {--memory-limit=256 : Limite de memória em MB}';
```

#### Partes Mais Importantes

### 1. Validação de Segurança do Path
```php
// Previne Path Traversal Attack
if ($customPath) {
    $realPath = realpath($customPath);
    $basePath = realpath(base_path());
    
    if (!$realPath || !str_starts_with($realPath, $basePath)) {
        $this->error('Path inválido ou fora do diretório do projeto');
        return 1;
    }
}
```

**Por que é crítico:**
- Previne acesso a arquivos fora do projeto
- Valida que o path é real e existe
- Proteção contra ataques de directory traversal

### 2. Gestão de Memória com Cache Limitado
```php
// Caches com tamanho controlado
private $distritosCache = [];     // ~29 entries
private $concelhosCache = [];     // ~298 entries
private $localidadesCache = [];   // Limited to 10,000
private $freguesiasCache = [];    // Limited to 10,000

private function clearCachePeriodically()
{
    if (count($this->localidadesCache) > 10000) {
        // Cria novo array ao invés de slice (libera memória)
        $newCache = [];
        $counter = 0;
        foreach (array_reverse($this->localidadesCache, true) as $key => $value) {
            if (++$counter > 5000) break;
            $newCache[$key] = $value;
        }
        $this->localidadesCache = $newCache;
        unset($newCache);
    }
    gc_collect_cycles();
}
```

**Otimizações:**
- Cache apenas datasets pequenos completamente
- Limpa cache periodicamente (a cada 50,000 linhas)
- Força garbage collection para liberar memória

### 3. Processamento em Batch com Transações
```php
private function processBulkInserts($localidades, $freguesias, $codigosPostais)
{
    DB::transaction(function() use ($localidades, $freguesias, $codigosPostais) {
        // Bulk insert localidades com UPSERT
        if (!empty($localidades)) {
            DB::table('localidades')->upsert(
                array_values($localidades),
                ['codigo_distrito', 'codigo_concelho', 'codigo_localidade'],
                ['nome', 'updated_at']
            );
        }
        
        // Códigos postais em chunks menores
        if (!empty($codigosPostais)) {
            $chunks = array_chunk($codigosPostais, 1000);
            foreach ($chunks as $chunk) {
                DB::table('codigos_postais')->insert(array_values($chunk));
            }
        }
    });
}
```

**Benefícios:**
- Transações garantem consistência
- UPSERT evita duplicatas
- Chunks de 1000 registros otimizam MySQL

### 4. Tratamento de Múltiplos Endereços por CP
```php
// Identificação de endereço único
$cpKey = "{$data['cp4']}_{$data['cp3']}";
$morada = $this->buildMoradaCompleta($data);

// Chave única incluindo designação e morada
$addressKey = $cpKey . '_' . md5($data['designacao_postal'] . '_' . $morada);

// Marca primeiro endereço como principal
if (!isset($batchCodigosPostais[$addressKey])) {
    $isPrimary = !isset($this->postalCodesSeen[$cpKey]);
    if ($isPrimary) {
        $this->postalCodesSeen[$cpKey] = true;
        $this->stats['unique_postal_codes']++;
    }
    
    $batchCodigosPostais[$addressKey] = [
        // ... dados do endereço
        'is_primary' => $isPrimary,
    ];
}
```

**Lógica Importante:**
- Detecta duplicatas reais (mesmo CP + designação + morada)
- Primeiro endereço de cada CP é marcado como principal
- Permite múltiplos endereços válidos por código postal

### 5. Conversão de Encoding Segura
```php
private function convertEncoding($text)
{
    if (empty($text)) return $text;
    
    // Valida entrada
    if (!is_string($text) || strlen($text) > 1000) {
        return '';
    }
    
    // Detecta encoding atual
    $currentEncoding = mb_detect_encoding($text, ['ISO-8859-1', 'UTF-8', 'ASCII'], true);
    
    // Converte apenas se necessário
    if ($currentEncoding === 'ISO-8859-1') {
        try {
            return mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        } catch (\Exception $e) {
            Log::warning('Erro ao converter encoding', ['text' => substr($text, 0, 50)]);
            return $text;
        }
    }
    
    return $text;
}
```

**Segurança:**
- Valida tamanho máximo da string
- Detecta encoding antes de converter
- Tratamento de erros com fallback

### 6. Atualização de Foreign Keys em Massa
```php
private function updateForeignKeys()
{
    // Atualiza localidade_id
    DB::statement("
        UPDATE codigos_postais cp
        INNER JOIN localidades l ON 
            cp.codigo_distrito = l.codigo_distrito AND
            cp.codigo_concelho = l.codigo_concelho AND
            cp.codigo_localidade = l.codigo_localidade
        SET cp.localidade_id = l.id
        WHERE cp.localidade_id IS NULL
    ");
    
    // Atualiza freguesia_id baseado na mais frequente
    DB::statement("
        UPDATE localidades l
        INNER JOIN (
            SELECT 
                cp.localidade_id,
                cp.freguesia_id,
                COUNT(*) as freq
            FROM codigos_postais cp
            WHERE cp.localidade_id IS NOT NULL 
            AND cp.freguesia_id IS NOT NULL
            GROUP BY cp.localidade_id, cp.freguesia_id
        ) AS freq_table ON l.id = freq_table.localidade_id
        SET l.freguesia_id = freq_table.freguesia_id
        WHERE l.freguesia_id IS NULL
    ");
}
```

**Estratégia:**
- Updates em massa são mais eficientes que individual
- Usa JOINs para performance
- Determina freguesia por frequência estatística

## Otimizações de Performance

### 1. Batch Processing
- **Tamanho do Batch**: 5000 registros
- **Benefício**: Reduz round-trips ao banco
- **Memória**: Mantém uso consistente ~256MB

### 2. Índices Estratégicos
```sql
-- Busca por código postal completo
INDEX idx_cp_full (cp4, cp3)

-- Busca de endereço principal
INDEX cp_primary_lookup (cp4, cp3, is_primary)

-- Verificação de unicidade
INDEX address_uniqueness_check (cp4, cp3, designacao_postal, morada)
```

### 3. Memory Management
- Cache limitado e rotativo
- Garbage collection forçado
- Unset explícito de variáveis grandes

### 4. Database Optimizations
- UPSERT ao invés de INSERT/UPDATE separados
- Transações para consistência
- Raw SQL para updates em massa

## Segurança

### Proteções Implementadas

1. **Path Traversal Prevention**
   - Validação de caminhos com realpath()
   - Verificação de boundaries do projeto

2. **Memory Limit Validation**
   ```php
   $this->memoryLimit = max(128, min(2048, $memoryLimit));
   ```

3. **SQL Injection Protection**
   - Uso de Eloquent ORM
   - Prepared statements
   - Sem concatenação de strings em SQL

4. **Cascade Delete Prevention**
   - ON DELETE RESTRICT para relações críticas
   - ON DELETE SET NULL para opcionais

5. **Input Validation**
   - Validação de encoding
   - Limite de tamanho de strings
   - Skip de registros inválidos

## Métricas e Resultados

### Performance
```
Tempo Total: ~3-5 minutos
Registros/Segundo: ~1,300
Memória Máxima: 277MB
Memória Média: 256MB
```

### Dados Importados
```
Distritos:              29
Concelhos:             298
Localidades:        23,516
Freguesias:          2,851
Códigos Postais:   236,348
CP Únicos:         150,751
Duplicados:         18,154
```

### Taxa de Sucesso
```
Total Esperado:    326,647 linhas
Total Válido:      236,348 endereços
Taxa de Import:    100% dos válidos
Rejeições:         90,299 (campos inválidos/incompletos)
```

## Troubleshooting

### Problema: Memória Excedida
**Solução:**
```bash
php artisan import:ctt-data --memory-limit=512 --batch-size=1000
```

### Problema: Importação Lenta
**Verificar:**
1. Índices do banco criados corretamente
2. Tamanho do batch (reduzir se necessário)
3. Cache do MySQL configurado

### Problema: Caracteres Especiais
**Verificar:**
1. Encoding do arquivo fonte (deve ser ISO-8859-1)
2. Collation do banco (utf8mb4_unicode_ci)
3. Conexão MySQL charset (utf8mb4)

### Problema: Foreign Key Constraint Failed
**Causa:** Tentativa de deletar registro pai com filhos
**Solução:** Sistema já protegido com ON DELETE RESTRICT

## Comandos Úteis

```bash
# Importação padrão
php artisan import:ctt-data

# Forçar reimportação
php artisan import:ctt-data --force

# Path customizado
php artisan import:ctt-data --path=/caminho/dados

# Otimização de memória
php artisan import:ctt-data --memory-limit=512 --batch-size=2000

# Verificar estrutura
php artisan migrate:status
php artisan db:table codigos_postais
```

## Conclusão

Sistema robusto e otimizado para importação de dados CTT com:
- ✅ Performance enterprise-grade
- ✅ Segurança contra vulnerabilidades comuns
- ✅ Flexibilidade para múltiplos endereços
- ✅ Proteção de integridade de dados
- ✅ Monitoramento e logging completo

Pronto para produção com capacidade de processar o dataset completo dos CTT em minutos.