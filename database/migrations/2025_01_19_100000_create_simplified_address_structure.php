<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela de distritos - Essencial (20 registros)
        Schema::create('distritos', function (Blueprint $table) {
            $table->string('codigo', 2)->primary();
            $table->string('nome', 100);
            $table->timestamps();
            
            $table->index('nome');
        });

        // Tabela de concelhos - Essencial (300+ registros)
        Schema::create('concelhos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_distrito', 2);
            $table->string('codigo_concelho', 2);
            $table->string('nome', 100);
            $table->timestamps();
            
            $table->foreign('codigo_distrito')->references('codigo')->on('distritos')->onDelete('cascade');
            $table->unique(['codigo_distrito', 'codigo_concelho']);
            $table->index('nome');
            $table->index('codigo_distrito');
        });

        // Tabela de localidades - Essencial (50k+ registros)
        Schema::create('localidades', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_distrito', 2);
            $table->string('codigo_concelho', 2);
            $table->string('codigo_localidade', 10);
            $table->string('nome', 150);
            $table->timestamps();
            
            $table->foreign('codigo_distrito')->references('codigo')->on('distritos')->onDelete('cascade');
            $table->foreign(['codigo_distrito', 'codigo_concelho'])
                  ->references(['codigo_distrito', 'codigo_concelho'])
                  ->on('concelhos')
                  ->onDelete('cascade');
            $table->unique(['codigo_distrito', 'codigo_concelho', 'codigo_localidade']);
            $table->index('nome');
            $table->index(['codigo_distrito', 'codigo_concelho']);
        });

        // Tabela de códigos postais - Simplificada
        // Apenas dados essenciais para formulário
        Schema::create('codigos_postais', function (Blueprint $table) {
            $table->id();
            $table->string('cp4', 4);
            $table->string('cp3', 3);
            $table->string('codigo_distrito', 2);
            $table->string('codigo_concelho', 2);
            $table->string('codigo_localidade', 10);
            $table->unsignedBigInteger('localidade_id')->nullable();
            $table->string('designacao_postal', 200);
            $table->string('morada_completa', 500)->nullable(); // Campo combinado opcional
            $table->timestamps();
            
            $table->foreign('codigo_distrito')->references('codigo')->on('distritos')->onDelete('cascade');
            $table->foreign('localidade_id')->references('id')->on('localidades')->onDelete('set null');
            
            // Índices otimizados para buscas comuns
            $table->unique(['cp4', 'cp3']);
            $table->index('cp4');
            $table->index(['cp4', 'cp3']); // Busca por código postal completo
            $table->index('designacao_postal');
            $table->index(['codigo_distrito', 'codigo_concelho']);
            $table->index('localidade_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_postais');
        Schema::dropIfExists('localidades');
        Schema::dropIfExists('concelhos');
        Schema::dropIfExists('distritos');
    }
};