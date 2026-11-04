<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('usuarios') && Schema::hasColumn('usuarios', 'situacao_funcional')) {
            DB::statement("ALTER TABLE usuarios MODIFY COLUMN situacao_funcional VARCHAR(60) NOT NULL DEFAULT 'ESTATUTARIO_SERVIDOR'");

            // Classificar Procuradores do Estado (Dr./Dra. e titulares de Procuradorias Especializadas/Regionais)
            DB::table('usuarios')
                ->where(function ($q) {
                    $q->where('nome', 'like', 'Dr.%')
                      ->orWhere('nome', 'like', 'Dra.%')
                      ->orWhereIn('matricula', [
                          'PGE-00104', // CS
                          'PGE-00106', // ESAP
                          'PGE-00112', // PA
                          'PGE-00114', // PCF
                          'PGE-00115', // PDA
                          'PGE-00117', // PERJP
                          'PGE-00118', // PGA
                          'PGE-00125', // PRI
                          'PGE-00126', // PRT
                          'PGE-00127', // PSAU
                          'PGE-00129', // PT
                      ]);
                })
                ->update(['situacao_funcional' => 'ESTATUTARIO_PROCURADOR']);

            // Classificar demais registros ATIVO_PERMANENTE para ESTATUTARIO_SERVIDOR
            DB::table('usuarios')
                ->where('situacao_funcional', 'ATIVO_PERMANENTE')
                ->update(['situacao_funcional' => 'ESTATUTARIO_SERVIDOR']);

            // Normalizar eventual RESIDENCIA_E_PMM legado para RESIDENCIA_JURIDICA
            DB::table('usuarios')
                ->where('situacao_funcional', 'RESIDENCIA_E_PMM')
                ->update(['situacao_funcional' => 'RESIDENCIA_JURIDICA']);
        }
    }

    public function down(): void
    {
        // Mantém VARCHAR(60) por compatibilidade com novos regimes da PGE-MA
    }
};
