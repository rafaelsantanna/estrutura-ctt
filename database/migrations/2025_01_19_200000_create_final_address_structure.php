<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estrutura Final Otimizada - 5 Tabelas
     * Atende todos os requisitos do tech lead:
     * - Hierarquia: distrito > concelho > freguesia > localidade
     * - Busca por código postal tipo GeoAPI
     * - Simples mas completa
     */
    public function up(): void
    {
        // 1. DISTRITOS - Base da hierarquia (20 registros)
        Schema::create('distritos', function (Blueprint $table) {
            $table->string('codigo', 2)->primary();
            $table->string('nome', 100);
            $table->timestamps();
            
            $table->index('nome');
        });

        // 2. CONCELHOS - Subdivisão dos distritos (300+ registros)
        Schema::create('concelhos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_distrito', 2);
            $table->string('codigo_concelho', 2);
            $table->string('nome', 100);
            $table->timestamps();
            
            $table->foreign('codigo_distrito')->references('codigo')->on('distritos')->onDelete('restrict');
            $table->unique(['codigo_distrito', 'codigo_concelho']);
            $table->index('nome');
            $table->index('codigo_distrito');
        });

        // 3. FREGUESIAS - Subdivisão dos concelhos (4k+ registros)
        Schema::create('freguesias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_distrito', 2);
            $table->string('codigo_concelho', 2);
            $table->string('codigo_freguesia', 6)->nullable();
            $table->string('nome', 150);
            $table->timestamps();
            
            $table->foreign('codigo_distrito')->references('codigo')->on('distritos')->onDelete('restrict');
            $table->foreign(['codigo_distrito', 'codigo_concelho'])
                  ->references(['codigo_distrito', 'codigo_concelho'])
                  ->on('concelhos')
                  ->onDelete('restrict');
            
            $table->unique(['codigo_distrito', 'codigo_concelho', 'nome']);
            $table->index('nome');
            $table->index(['codigo_distrito', 'codigo_concelho']);
        });

        // 4. LOCALIDADES - Cidades, vilas e aldeias (50k+ registros)
        Schema::create('localidades', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_distrito', 2);
            $table->string('codigo_concelho', 2);
            $table->string('codigo_localidade', 10);
            $table->string('nome', 150);
            $table->unsignedBigInteger('freguesia_id')->nullable();
            $table->timestamps();
            
            $table->foreign('codigo_distrito')->references('codigo')->on('distritos')->onDelete('restrict');
            $table->foreign(['codigo_distrito', 'codigo_concelho'])
                  ->references(['codigo_distrito', 'codigo_concelho'])
                  ->on('concelhos')
                  ->onDelete('restrict');
            $table->foreign('freguesia_id')->references('id')->on('freguesias')->onDelete('set null');
            
            $table->unique(['codigo_distrito', 'codigo_concelho', 'codigo_localidade'], 'localidades_unique');
            $table->index('nome');
            $table->index(['codigo_distrito', 'codigo_concelho']);
            $table->index('freguesia_id');
        });

        // 5. CÓDIGOS POSTAIS - Busca tipo GeoAPI (300k+ registros)
        Schema::create('codigos_postais', function (Blueprint $table) {
            $table->id();
            $table->string('cp4', 4);
            $table->string('cp3', 3);
            $table->string('codigo_distrito', 2);
            $table->string('codigo_concelho', 2);
            $table->string('codigo_localidade', 10);
            $table->unsignedBigInteger('localidade_id')->nullable();
            $table->unsignedBigInteger('freguesia_id')->nullable();
            $table->string('designacao_postal', 200);
            $table->string('morada', 500)->nullable(); // Morada simplificada se houver
            $table->boolean('is_primary')->default(false); // Identifica endereço principal para cada CP
            $table->timestamps();
            
            $table->foreign('codigo_distrito')->references('codigo')->on('distritos')->onDelete('restrict');
            $table->foreign('localidade_id')->references('id')->on('localidades')->onDelete('set null');
            $table->foreign('freguesia_id')->references('id')->on('freguesias')->onDelete('set null');
            
            // Índices otimizados para busca tipo GeoAPI
            // NOTA: Removido unique constraint em ['cp4', 'cp3'] para permitir múltiplos endereços por código postal
            $table->index('cp4');
            $table->index(['cp4', 'cp3']); // Busca por código postal completo
            $table->index(['cp4', 'cp3', 'is_primary'], 'cp_primary_lookup'); // Busca endereço principal
            $table->index(['cp4', 'cp3', 'designacao_postal'], 'cp_designacao_lookup'); // Busca com designação
            $table->index(['cp4', 'cp3', 'designacao_postal', 'morada'], 'address_uniqueness_check'); // Verificação de unicidade real
            $table->index('designacao_postal');
            $table->index(['codigo_distrito', 'codigo_concelho']);
            $table->index('localidade_id');
            $table->index('freguesia_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_postais');
        Schema::dropIfExists('localidades');
        Schema::dropIfExists('freguesias');
        Schema::dropIfExists('concelhos');
        Schema::dropIfExists('distritos');
    }
};