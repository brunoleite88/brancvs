<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cockpit_gabinete_configs')) {
            Schema::create('cockpit_gabinete_configs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('chave', 60)->unique()->default('default');
                $table->string('titulo', 180)->default('Cockpit Executivo do Gabinete');
                $table->string('subtitulo', 300)->default('Monitoramento Estratégico Integrado da PGE-MA (Ciclo 2026–2030)');
                $table->json('widgets')->nullable();
                $table->json('macro_areas')->nullable();
                $table->json('regras_farol')->nullable();
                $table->json('opcoes')->nullable();
                $table->uuid('atualizado_por_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Registrar capacidade granular para configuração do Cockpit (MOD_PAINEL_COCKPIT_CFG)
        $now = now();
        $modPainelGer = DB::table('tipos_capacidades')->where('codigo', 'MOD_PAINEL_GER')->first();
        $codigo = 'MOD_PAINEL_COCKPIT_CFG';

        $tipoCap = DB::table('tipos_capacidades')->where('codigo', $codigo)->first();
        if (!$tipoCap) {
            $tipoCapId = (string) Str::uuid();
            DB::table('tipos_capacidades')->insert([
                'id' => $tipoCapId,
                'codigo' => $codigo,
                'descricao' => 'Permite configurar widgets, macro-áreas e regras de farol do Cockpit do Gabinete',
                'grupo_id' => $modPainelGer?->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $tipoCapId = $tipoCap->id;
        }

        // Conceder aos perfis Desenvolvedor (0), Administrador Master (1) e Administrador Negocial (2)
        $perfisIds = DB::table('perfis')->whereIn('nivel', [0, 1, 2])->whereNull('deleted_at')->pluck('id');
        foreach ($perfisIds as $perfilId) {
            $jaTem = DB::table('capacidades')
                ->where('perfil_id', $perfilId)
                ->where('tipo_capacidade_id', $tipoCapId)
                ->whereNull('deleted_at')
                ->exists();

            if (!$jaTem) {
                DB::table('capacidades')->insert([
                    'id' => (string) Str::uuid(),
                    'perfil_id' => $perfilId,
                    'tipo_capacidade_id' => $tipoCapId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $tipoCap = DB::table('tipos_capacidades')->where('codigo', 'MOD_PAINEL_COCKPIT_CFG')->first();
        if ($tipoCap) {
            DB::table('capacidades')->where('tipo_capacidade_id', $tipoCap->id)->delete();
            DB::table('tipos_capacidades')->where('id', $tipoCap->id)->delete();
        }
        Schema::dropIfExists('cockpit_gabinete_configs');
    }
};
