<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('planos_entregas_repactuacoes')) {
            return;
        }

        Schema::create('planos_entregas_repactuacoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plano_entrega_id');
            $table->uuid('plano_entrega_entrega_id')->nullable();
            $table->uuid('unidade_id');
            $table->string('ciclo', 20)->default('2026-T1');
            $table->string('tipo', 30)->default('PRAZO');
            $table->string('motivo', 50)->default('OUTRO');
            $table->text('descricao_ajuste');
            $table->text('justificativa');
            $table->dateTime('data_anterior')->nullable();
            $table->dateTime('data_nova')->nullable();
            $table->text('meta_anterior')->nullable();
            $table->text('meta_nova')->nullable();
            $table->enum('status', ['PENDENTE', 'HOMOLOGADO', 'REJEITADO'])->default('PENDENTE');
            $table->uuid('solicitante_id');
            $table->uuid('analisador_id')->nullable();
            $table->text('parecer_analise')->nullable();
            $table->dateTime('data_homologacao')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('plano_entrega_id');
            $table->index('plano_entrega_entrega_id');
            $table->index('unidade_id');
            $table->index('ciclo');
            $table->index('status');
            $table->index('solicitante_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planos_entregas_repactuacoes');
    }
};
