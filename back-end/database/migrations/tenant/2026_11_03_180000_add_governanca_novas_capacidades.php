<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // 1. Definição das novas capacidades
        // Root group IDs
        $modProjId = (string) Str::uuid();
        $modRepactId = (string) Str::uuid();
        $modSeiId = (string) Str::uuid();

        // Pai existente: MOD_PAINEL_GER
        $modPainelGer = DB::table('tipos_capacidades')->where('codigo', 'MOD_PAINEL_GER')->first();
        $modPainelGerId = $modPainelGer?->id;

        $capacidades = [
            // Sub-capacidade de Painel Gerencial
            [
                'id' => (string) Str::uuid(),
                'codigo' => 'MOD_PAINEL_COCKPIT',
                'descricao' => 'Permite visualizar o Cockpit Executivo do Gabinete e Alta Gestão',
                'grupo_id' => $modPainelGerId,
            ],

            // Módulo de Projetos Estruturantes
            [
                'id' => $modProjId,
                'codigo' => 'MOD_PROJ',
                'descricao' => 'Módulo de Projetos Estruturantes da PGE-MA',
                'grupo_id' => null,
            ],
            [
                'id' => (string) Str::uuid(),
                'codigo' => 'MOD_PROJ_CONS',
                'descricao' => 'Permite consultar Projetos Estruturantes, Gantt e Linha do Tempo',
                'grupo_id' => $modProjId,
            ],
            [
                'id' => (string) Str::uuid(),
                'codigo' => 'MOD_PROJ_INCL',
                'descricao' => 'Permite incluir novos Projetos Estruturantes e Grupos de Trabalho',
                'grupo_id' => $modProjId,
            ],
            [
                'id' => (string) Str::uuid(),
                'codigo' => 'MOD_PROJ_EDT',
                'descricao' => 'Permite editar Projetos Estruturantes, KRs e Marcos Críticos',
                'grupo_id' => $modProjId,
            ],
            [
                'id' => (string) Str::uuid(),
                'codigo' => 'MOD_PROJ_EXCL',
                'descricao' => 'Permite excluir Projetos Estruturantes',
                'grupo_id' => $modProjId,
            ],
            [
                'id' => (string) Str::uuid(),
                'codigo' => 'MOD_PROJ_VINC_SEI',
                'descricao' => 'Permite vincular processos administrativos SEI-MA aos Projetos',
                'grupo_id' => $modProjId,
            ],

            // Módulo de Repactuação e Monitoramento Periódico
            [
                'id' => $modRepactId,
                'codigo' => 'MOD_REPACTUACAO',
                'descricao' => 'Módulo de Repactuação e Monitoramento Periódico',
                'grupo_id' => null,
            ],
            [
                'id' => (string) Str::uuid(),
                'codigo' => 'MOD_REPACT_CONS',
                'descricao' => 'Permite consultar repactuações, histórico e trilha de auditoria',
                'grupo_id' => $modRepactId,
            ],
            [
                'id' => (string) Str::uuid(),
                'codigo' => 'MOD_REPACT_SOLICITAR',
                'descricao' => 'Permite solicitar repactuação de metas, prazos e escopo para entregas',
                'grupo_id' => $modRepactId,
            ],
            [
                'id' => (string) Str::uuid(),
                'codigo' => 'MOD_REPACT_DELIBERAR',
                'descricao' => 'Permite homologar ou rejeitar pareceres na fila de deliberação (PGAAE/Gabinete)',
                'grupo_id' => $modRepactId,
            ],

            // Módulo de Integração com Processos Administrativos SEI-MA
            [
                'id' => $modSeiId,
                'codigo' => 'MOD_SEI',
                'descricao' => 'Módulo de Integração com Processos Administrativos (SEI-MA)',
                'grupo_id' => null,
            ],
            [
                'id' => (string) Str::uuid(),
                'codigo' => 'MOD_SEI_VINCULAR',
                'descricao' => 'Permite vincular processos administrativos SEI-MA a planos e entregas',
                'grupo_id' => $modSeiId,
            ],
            [
                'id' => (string) Str::uuid(),
                'codigo' => 'MOD_SEI_EMITIR_DESPACHO',
                'descricao' => 'Permite emitir e baixar Termos e Despachos Oficiais em PDF',
                'grupo_id' => $modSeiId,
            ],
        ];

        // 2. Inserir em tipos_capacidades (evitando duplicatas se já existirem)
        foreach ($capacidades as $cap) {
            $exists = DB::table('tipos_capacidades')->where('codigo', $cap['codigo'])->first();
            if (!$exists) {
                DB::table('tipos_capacidades')->insert([
                    'id' => $cap['id'],
                    'codigo' => $cap['codigo'],
                    'descricao' => $cap['descricao'],
                    'grupo_id' => $cap['grupo_id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // 3. Mapear códigos para IDs atualizados
        $codigoMap = DB::table('tipos_capacidades')
            ->whereIn('codigo', array_column($capacidades, 'codigo'))
            ->pluck('id', 'codigo');

        // 4. Obter perfis
        $perfis = DB::table('perfis')->get()->keyBy('nivel');

        $perfilDev = $perfis->get(0);      // Desenvolvedor
        $perfilMaster = $perfis->get(1);   // Administrador Master
        $perfilNegocial = $perfis->get(2); // Administrador Negocial
        $perfilUnidade = $perfis->get(3);  // Unidade
        $perfilConsulta = $perfis->get(7); // Consulta

        // Regras de atribuição inicial:
        $distribuicao = [
            // Desenvolvedor e Master recebem TODAS
            'todos' => [
                'perfis' => array_filter([$perfilDev?->id, $perfilMaster?->id]),
                'codigos' => array_column($capacidades, 'codigo'),
            ],
            // Negocial
            'negocial' => [
                'perfis' => array_filter([$perfilNegocial?->id]),
                'codigos' => [
                    'MOD_PAINEL_COCKPIT',
                    'MOD_PROJ', 'MOD_PROJ_CONS', 'MOD_PROJ_INCL', 'MOD_PROJ_EDT', 'MOD_PROJ_VINC_SEI',
                    'MOD_REPACTUACAO', 'MOD_REPACT_CONS', 'MOD_REPACT_SOLICITAR', 'MOD_REPACT_DELIBERAR',
                    'MOD_SEI', 'MOD_SEI_VINCULAR', 'MOD_SEI_EMITIR_DESPACHO',
                ],
            ],
            // Unidade (gestores setoriais)
            'unidade' => [
                'perfis' => array_filter([$perfilUnidade?->id]),
                'codigos' => [
                    'MOD_PROJ', 'MOD_PROJ_CONS',
                    'MOD_REPACTUACAO', 'MOD_REPACT_CONS', 'MOD_REPACT_SOLICITAR',
                    'MOD_SEI', 'MOD_SEI_EMITIR_DESPACHO',
                ],
            ],
            // Consulta
            'consulta' => [
                'perfis' => array_filter([$perfilConsulta?->id]),
                'codigos' => [
                    'MOD_PROJ_CONS',
                    'MOD_REPACT_CONS',
                ],
            ],
        ];

        foreach ($distribuicao as $grupo) {
            foreach ($grupo['perfis'] as $perfilId) {
                foreach ($grupo['codigos'] as $codigo) {
                    $tipoCapId = $codigoMap->get($codigo);
                    if (!$tipoCapId) continue;

                    $jaTem = DB::table('capacidades')
                        ->where('perfil_id', $perfilId)
                        ->where('tipo_capacidade_id', $tipoCapId)
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
        }
    }

    public function down(): void
    {
        $codigos = [
            'MOD_PAINEL_COCKPIT',
            'MOD_PROJ', 'MOD_PROJ_CONS', 'MOD_PROJ_INCL', 'MOD_PROJ_EDT', 'MOD_PROJ_EXCL', 'MOD_PROJ_VINC_SEI',
            'MOD_REPACTUACAO', 'MOD_REPACT_CONS', 'MOD_REPACT_SOLICITAR', 'MOD_REPACT_DELIBERAR',
            'MOD_SEI', 'MOD_SEI_VINCULAR', 'MOD_SEI_EMITIR_DESPACHO',
        ];

        $tipoCapIds = DB::table('tipos_capacidades')->whereIn('codigo', $codigos)->pluck('id');

        DB::table('capacidades')->whereIn('tipo_capacidade_id', $tipoCapIds)->delete();
        DB::table('tipos_capacidades')->whereIn('id', $tipoCapIds)->delete();
    }
};
