<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportCttData extends Command
{
    protected $signature = 'import:ctt-data 
                            {--force : Força a reimportação, apagando dados existentes}
                            {--path= : Caminho alternativo para os arquivos de dados}
                            {--batch-size=5000 : Tamanho do batch para processamento}
                            {--memory-limit=256 : Limite de memória em MB}';

    protected $description = 'Importa dados CTT com estrutura completa (5 tabelas: distrito > concelho > freguesia > localidade > CP)';

    private $dataPath;
    private $batchSize;
    private $memoryLimit;
    
    // Cache limitado para evitar memory leaks
    private $distritosCache = [];
    private $concelhosCache = [];
    private $localidadesCache = [];
    private $freguesiasCache = [];
    
    // Contadores para estatísticas
    private $stats = [
        'distritos' => 0,
        'concelhos' => 0,
        'localidades' => 0,
        'freguesias' => 0,
        'codigos_postais' => 0,
    ];

    public function handle()
    {
        $this->dataPath = $this->option('path') ?: base_path('todos_cp');
        $this->batchSize = (int) $this->option('batch-size');
        $this->memoryLimit = (int) $this->option('memory-limit');
        
        // Configura limite de memória
        ini_set('memory_limit', $this->memoryLimit . 'M');
        
        if (!is_dir($this->dataPath)) {
            $this->error("Diretório de dados não encontrado: {$this->dataPath}");
            return 1;
        }

        $this->info('🚀 Iniciando importação OTIMIZADA de dados CTT...');
        $this->info("📊 Batch size: {$this->batchSize} | Memory limit: {$this->memoryLimit}MB");
        $this->newLine();

        try {
            if ($this->option('force')) {
                $this->warn('⚠️  Modo force ativado. Limpando dados existentes...');
                $this->cleanDatabase();
            }

            // Importações sequenciais com transações menores
            $this->importDistritos();
            $this->importConcelhos();
            $this->importCodigosPostaisOptimized();
            
            $this->newLine();
            $this->info('✅ Importação concluída com sucesso!');
            $this->displaySummary();
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Erro durante a importação: ' . $e->getMessage());
            Log::error('Erro na importação CTT otimizada', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    private function cleanDatabase()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        $tables = ['codigos_postais', 'localidades', 'freguesias', 'concelhos', 'distritos'];
        $bar = $this->output->createProgressBar(count($tables));
        
        foreach ($tables as $table) {
            DB::table($table)->truncate();
            $bar->advance();
        }
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $bar->finish();
        $this->newLine();
    }

    private function importDistritos()
    {
        $this->info('📍 Importando distritos...');
        
        $file = $this->dataPath . '/distritos.txt';
        if (!file_exists($file)) {
            throw new \Exception("Arquivo de distritos não encontrado: $file");
        }

        $data = [];
        foreach ($this->readFileLines($file) as $line) {
            $parts = explode(';', $line);
            if (count($parts) >= 2) {
                $codigo = trim($parts[0]);
                $nome = $this->convertEncoding(trim($parts[1]));
                
                $data[] = [
                    'codigo' => $codigo,
                    'nome' => $nome,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                
                $this->distritosCache[$codigo] = true;
            }
        }
        
        // Bulk insert
        if (!empty($data)) {
            DB::table('distritos')->insertOrIgnore($data);
            $this->stats['distritos'] = count($data);
        }
        
        $this->info("✓ {$this->stats['distritos']} distritos importados");
    }

    private function importConcelhos()
    {
        $this->info('🏘️ Importando concelhos...');
        
        $file = $this->dataPath . '/concelhos.txt';
        if (!file_exists($file)) {
            throw new \Exception("Arquivo de concelhos não encontrado: $file");
        }

        $data = [];
        foreach ($this->readFileLines($file) as $line) {
            $parts = explode(';', $line);
            if (count($parts) >= 3) {
                $codigoConcelho = trim($parts[0]);
                $codigoDistrito = trim($parts[1]);
                $nome = $this->convertEncoding(trim($parts[2]));
                
                // Valida se distrito existe
                if (!isset($this->distritosCache[$codigoDistrito])) {
                    $this->warn("Distrito não encontrado: $codigoDistrito para concelho: $nome");
                    continue;
                }
                
                $data[] = [
                    'codigo_distrito' => $codigoDistrito,
                    'codigo_concelho' => $codigoConcelho,
                    'nome' => $nome,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                
                $this->concelhosCache["{$codigoDistrito}_{$codigoConcelho}"] = true;
            }
        }
        
        // Bulk insert
        if (!empty($data)) {
            DB::table('concelhos')->insertOrIgnore($data);
            $this->stats['concelhos'] = count($data);
        }
        
        $this->info("✓ {$this->stats['concelhos']} concelhos importados");
    }

    private function importCodigosPostaisOptimized()
    {
        $this->info('📮 Importando códigos postais, localidades e freguesias...');
        
        $file = $this->dataPath . '/todos_cp.txt';
        if (!file_exists($file)) {
            throw new \Exception("Arquivo de códigos postais não encontrado: $file");
        }

        // Estima total de linhas (aprox.)
        $estimatedLines = 300000;
        $bar = $this->output->createProgressBar($estimatedLines);
        
        $batchLocalidades = [];
        $batchFreguesias = [];
        $batchCodigosPostais = [];
        
        $lineCount = 0;
        $processedCount = 0;
        
        foreach ($this->readFileLines($file) as $line) {
            $lineCount++;
            $parts = explode(';', $line);
            
            if (count($parts) >= 17) {
                $data = $this->parseCodigoPostalLine($parts);
                
                if ($data) {
                    // Prepara dados para bulk insert
                    $localidadeKey = "{$data['codigo_distrito']}_{$data['codigo_concelho']}_{$data['codigo_localidade']}";
                    
                    if (!isset($this->localidadesCache[$localidadeKey])) {
                        $batchLocalidades[$localidadeKey] = [
                            'codigo_distrito' => $data['codigo_distrito'],
                            'codigo_concelho' => $data['codigo_concelho'],
                            'codigo_localidade' => $data['codigo_localidade'],
                            'nome' => $data['nome_localidade'],
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                        $this->localidadesCache[$localidadeKey] = true;
                    }
                    
                    // Freguesias (usando designacao_postal como nome da freguesia)
                    if (!empty($data['designacao_postal'])) {
                        $freguesiaKey = "{$data['codigo_distrito']}_{$data['codigo_concelho']}_{$data['designacao_postal']}";
                        if (!isset($this->freguesiasCache[$freguesiaKey])) {
                            $batchFreguesias[$freguesiaKey] = [
                                'codigo_distrito' => $data['codigo_distrito'],
                                'codigo_concelho' => $data['codigo_concelho'],
                                'nome' => $data['designacao_postal'],
                                'created_at' => now(),
                                'updated_at' => now()
                            ];
                            $this->freguesiasCache[$freguesiaKey] = true;
                        }
                    }
                    
                    // Código postal com morada simplificada
                    $cpKey = "{$data['cp4']}_{$data['cp3']}";
                    $morada = $this->buildMoradaCompleta($data);
                    
                    $batchCodigosPostais[$cpKey] = [
                        'codigo_distrito' => $data['codigo_distrito'],
                        'codigo_concelho' => $data['codigo_concelho'],
                        'codigo_localidade' => $data['codigo_localidade'],
                        'cp4' => $data['cp4'],
                        'cp3' => $data['cp3'],
                        'designacao_postal' => $data['designacao_postal'],
                        'morada' => $morada,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    
                    $processedCount++;
                }
            }
            
            // Processa batch quando atinge o limite
            if ($processedCount >= $this->batchSize) {
                $this->processBulkInserts($batchLocalidades, $batchFreguesias, $batchCodigosPostais);
                
                // Limpa batches
                $batchLocalidades = [];
                $batchFreguesias = [];
                $batchCodigosPostais = [];
                $processedCount = 0;
                
                // Limpa cache periodicamente para evitar memory leak
                if ($lineCount % 50000 === 0) {
                    $this->clearCachePeriodically();
                    $this->info("\n💾 Cache limpo após {$lineCount} linhas. Memória: " . $this->getMemoryUsage());
                }
            }
            
            $bar->advance();
        }
        
        // Processa últimos registros
        if ($processedCount > 0) {
            $this->processBulkInserts($batchLocalidades, $batchFreguesias, $batchCodigosPostais);
        }
        
        $bar->finish();
        $this->newLine();
        
        // Atualiza foreign keys após bulk insert
        $this->updateForeignKeys();
    }

    private function processBulkInserts($localidades, $freguesias, $codigosPostais)
    {
        DB::transaction(function() use ($localidades, $freguesias, $codigosPostais) {
            // Bulk insert localidades
            if (!empty($localidades)) {
                DB::table('localidades')->insertOrIgnore(array_values($localidades));
                $this->stats['localidades'] += count($localidades);
            }
            
            // Bulk insert freguesias
            if (!empty($freguesias)) {
                DB::table('freguesias')->insertOrIgnore(array_values($freguesias));
                $this->stats['freguesias'] += count($freguesias);
            }
            
            // Bulk insert códigos postais
            if (!empty($codigosPostais)) {
                $chunks = array_chunk($codigosPostais, 1000);
                foreach ($chunks as $chunk) {
                    DB::table('codigos_postais')->insertOrIgnore(array_values($chunk));
                }
                $this->stats['codigos_postais'] += count($codigosPostais);
            }
        });
    }

    private function buildMoradaCompleta($data)
    {
        // Constrói morada completa se houver dados de arruamento
        $morada = [];
        
        // Adiciona tipo de arruamento (Rua, Praça, etc.)
        if (!empty($data['arruamento']['tipo'])) {
            $morada[] = $data['arruamento']['tipo'];
        }
        
        // Adiciona preposições e título
        if (!empty($data['arruamento']['primeira_preposicao'])) {
            $morada[] = $data['arruamento']['primeira_preposicao'];
        }
        if (!empty($data['arruamento']['titulo'])) {
            $morada[] = $data['arruamento']['titulo'];
        }
        if (!empty($data['arruamento']['segunda_preposicao'])) {
            $morada[] = $data['arruamento']['segunda_preposicao'];
        }
        
        // Adiciona designação
        if (!empty($data['arruamento']['designacao'])) {
            $morada[] = $data['arruamento']['designacao'];
        }
        
        // Retorna morada combinada ou null se não houver dados
        return !empty($morada) ? implode(' ', $morada) : null;
    }

    private function updateForeignKeys()
    {
        $this->info('🔗 Atualizando foreign keys...');
        
        // Atualiza localidade_id nos códigos postais
        DB::statement("
            UPDATE codigos_postais cp
            INNER JOIN localidades l ON 
                cp.codigo_distrito = l.codigo_distrito AND
                cp.codigo_concelho = l.codigo_concelho AND
                cp.codigo_localidade = l.codigo_localidade
            SET cp.localidade_id = l.id
            WHERE cp.localidade_id IS NULL
        ");
        
        // Atualiza freguesia_id nos códigos postais
        DB::statement("
            UPDATE codigos_postais cp
            INNER JOIN freguesias f ON 
                cp.codigo_distrito = f.codigo_distrito AND
                cp.codigo_concelho = f.codigo_concelho AND
                cp.designacao_postal = f.nome
            SET cp.freguesia_id = f.id
            WHERE cp.freguesia_id IS NULL
        ");
        
        // Atualiza freguesia_id nas localidades (baseado na designação postal mais comum)
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
        
        $this->info('✓ Foreign keys atualizadas');
    }

    private function clearCachePeriodically()
    {
        // Mantém apenas últimos 10k registros no cache
        if (count($this->localidadesCache) > 10000) {
            $this->localidadesCache = array_slice($this->localidadesCache, -5000, null, true);
        }
        
        if (count($this->freguesiasCache) > 10000) {
            $this->freguesiasCache = array_slice($this->freguesiasCache, -5000, null, true);
        }
        
        // Força garbage collection
        gc_collect_cycles();
    }

    private function parseCodigoPostalLine($parts)
    {
        $codigoDistrito = trim($parts[0]);
        $codigoConcelho = trim($parts[1]);
        
        // Valida distrito
        if (!isset($this->distritosCache[$codigoDistrito])) {
            return null;
        }
        
        return [
            'codigo_distrito' => $codigoDistrito,
            'codigo_concelho' => $codigoConcelho,
            'codigo_localidade' => trim($parts[2]),
            'nome_localidade' => $this->convertEncoding(trim($parts[3])),
            'arruamento' => [
                'tipo' => $this->convertEncoding(trim($parts[5])),
                'primeira_preposicao' => $this->convertEncoding(trim($parts[6])),
                'titulo' => $this->convertEncoding(trim($parts[7])),
                'segunda_preposicao' => $this->convertEncoding(trim($parts[8])),
                'designacao' => $this->convertEncoding(trim($parts[9])),
            ],
            'cp4' => trim($parts[14]),
            'cp3' => trim($parts[15]),
            'designacao_postal' => $this->convertEncoding(trim($parts[16])),
        ];
    }

    private function readFileLines($file): \Generator
    {
        $handle = fopen($file, 'r');
        if (!$handle) {
            throw new \Exception("Não foi possível abrir o arquivo: $file");
        }
        
        while (($line = fgets($handle)) !== false) {
            yield trim($line);
        }
        
        fclose($handle);
    }

    private function convertEncoding($text)
    {
        if (empty($text)) {
            return $text;
        }
        
        // Força conversão de ISO-8859-1 para UTF-8 (padrão dos arquivos CTT)
        return mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
    }

    private function getMemoryUsage()
    {
        return round(memory_get_usage() / 1024 / 1024, 2) . 'MB / ' . 
               round(memory_get_peak_usage() / 1024 / 1024, 2) . 'MB peak';
    }

    private function displaySummary()
    {
        $this->table(
            ['Tabela', 'Registros Importados'],
            [
                ['Distritos', number_format($this->stats['distritos'])],
                ['Concelhos', number_format($this->stats['concelhos'])],
                ['Localidades', number_format($this->stats['localidades'])],
                ['Freguesias', number_format($this->stats['freguesias'])],
                ['Códigos Postais', number_format($this->stats['codigos_postais'])]
            ]
        );
        
        $this->info('💾 Memória utilizada: ' . $this->getMemoryUsage());
    }
}