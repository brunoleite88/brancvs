<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\Entidade;
use App\Models\Unidade;
use App\Models\Cidade;
use App\Models\EixoTematico;
use App\Models\Planejamento;
use App\Models\PlanejamentoObjetivo;
use App\Models\PlanoEntregaEntregaObjetivo;
use App\Models\Projeto;
use App\Models\TipoProjeto;

class PgemaSgpeSeeder extends Seeder
{
    public function run()
    {
        $jsonPath = __DIR__ . '/data/dados_sgpe.json';
        if (!file_exists($jsonPath)) {
            $this->command->error("Arquivo de dados não encontrado em {$jsonPath}");
            return;
        }

        $this->command->info("Lendo dados do SGPE em {$jsonPath}...");
        $data = json_decode(file_get_contents($jsonPath), true);
        if (!$data) {
            $this->command->error("Falha ao decodificar JSON do SGPE.");
            return;
        }

        $now = now();

        // 1. Localizar Cidade de São Luís/MA (IBGE 2111300)
        $cidade = Cidade::where('codigo_ibge', '2111300')->first()
            ?? Cidade::where('uf', 'MA')->first()
            ?? Cidade::first();

        // 2. Atualizar Entidade existente para PGE-MA
        $entidade = Entidade::find('52d78c7d-e0c1-422b-b094-2ca5958d5ac1');
        if ($entidade) {
            $entidade->update([
                'sigla' => 'PGE-MA',
                'nome' => 'Procuradoria Geral do Estado do Maranhão',
                'abrangencia' => 'ESTADUAL',
                'uf' => 'MA',
                'codigo_ibge' => $cidade ? $cidade->codigo_ibge : '2111300',
                'cidade_id' => $cidade ? $cidade->id : null,
            ]);
        } else {
            $entidade = Entidade::create([
                'id' => '52d78c7d-e0c1-422b-b094-2ca5958d5ac1',
                'sigla' => 'PGE-MA',
                'nome' => 'Procuradoria Geral do Estado do Maranhão',
                'abrangencia' => 'ESTADUAL',
                'codigo_ibge' => $cidade ? $cidade->codigo_ibge : '2111300',
                'uf' => 'MA',
                'carga_horaria_padrao' => 8,
                'gravar_historico_processo' => 0,
                'layout_formulario_atividade' => 'COMPLETO',
                'forma_contagem_carga_horaria' => 'DIA',
                'cidade_id' => $cidade ? $cidade->id : null,
            ]);
        }
        $this->command->info("Entidade PGE-MA configurada.");

        // 3. Atualizar Unidade Raiz para PGE-MA
        $unidadeRaiz = Unidade::find('4f705d83-5808-4240-8b92-39ca88139076');
        if ($unidadeRaiz) {
            $unidadeRaiz->update([
                'sigla' => 'PGE-MA',
                'nome' => 'Procuradoria Geral do Estado do Maranhão (Sede)',
                'codigo' => 'PGE-001',
                'instituidora' => 1,
                'entidade_id' => $entidade->id,
                'cidade_id' => $cidade ? $cidade->id : null,
                'unidade_pai_id' => null,
            ]);
        } else {
            $unidadeRaiz = Unidade::create([
                'id' => '4f705d83-5808-4240-8b92-39ca88139076',
                'sigla' => 'PGE-MA',
                'nome' => 'Procuradoria Geral do Estado do Maranhão (Sede)',
                'codigo' => 'PGE-001',
                'instituidora' => 1,
                'entidade_id' => $entidade->id,
                'cidade_id' => $cidade ? $cidade->id : null,
                'unidade_pai_id' => null,
            ]);
        }
        $this->command->info("Unidade Raiz PGE-MA configurada.");

        // 4. Cadastrar Unidades Setoriais do SGPE
        $unidadesMap = ['PGE-MA' => $unidadeRaiz->id];
        $unidadesList = $data['overview']['unidades'] ?? [];

        foreach ($unidadesList as $index => $u) {
            $sigla = trim($u['sigla'] ?? '');
            $nome = trim($u['nome'] ?? $sigla);
            if (empty($sigla)) continue;

            $unidadeObj = Unidade::withTrashed()->where('sigla', $sigla)->first();
            if ($unidadeObj) {
                $unidadeObj->restore();
                $unidadeObj->update([
                    'nome' => $nome,
                    'entidade_id' => $entidade->id,
                    'cidade_id' => $cidade ? $cidade->id : $unidadeObj->cidade_id,
                    'texto_complementar_plano' => $u['pauta_reuniao'] ?? $unidadeObj->texto_complementar_plano,
                ]);
            } else {
                $unidadeObj = Unidade::create([
                    'id' => (string) Str::uuid(),
                    'sigla' => $sigla,
                    'nome' => $nome,
                    'codigo' => sprintf('PGE-%03d', $index + 2),
                    'instituidora' => 0,
                    'entidade_id' => $entidade->id,
                    'unidade_pai_id' => $unidadeRaiz->id,
                    'cidade_id' => $cidade ? $cidade->id : null,
                    'texto_complementar_plano' => $u['pauta_reuniao'] ?? null,
                ]);
            }
            $unidadesMap[$sigla] = $unidadeObj->id;
        }
        $this->command->info(count($unidadesMap) . " unidades institucionais mapeadas.");

        // 5. Cadastrar os 9 Eixos Temáticos
        $coresEixos = [
            'E1' => ['#1E3A8A', 'bi bi-cash-stack'],
            'E2' => ['#047857', 'bi bi-gear-wide-connected'],
            'E3' => ['#B45309', 'bi bi-pie-chart-fill'],
            'E4' => ['#6D28D9', 'bi bi-emoji-smile'],
            'E5' => ['#2563EB', 'bi bi-check2-circle'],
            'E6' => ['#0284C7', 'bi bi-megaphone'],
            'E7' => ['#4F46E5', 'bi bi-cpu'],
            'E8' => ['#059669', 'bi bi-shield-check'],
            'E9' => ['#DB2777', 'bi bi-people'],
        ];

        $eixosMap = [];
        $eixosList = $data['overview']['por_eixo'] ?? [];

        foreach ($eixosList as $e) {
            $cod = trim($e['eixo_cod'] ?? '');
            $nome = trim($e['eixo'] ?? '');
            $corConfig = $coresEixos[$cod] ?? ['#3B82F6', 'bi bi-bookmark'];

            $eixoObj = EixoTematico::firstOrCreate(
                ['nome' => $nome],
                [
                    'id' => (string) Str::uuid(),
                    'cor' => $corConfig[0],
                    'icone' => $corConfig[1],
                    'descricao' => "{$nome} - PGE-MA (Ciclo 2026–2030)"
                ]
            );
            $eixosMap[$cod] = $eixoObj->id;
        }
        $this->command->info(count($eixosMap) . " Eixos Temáticos criados.");

        // 6. Cadastrar o Planejamento Estratégico Institucional (Ciclo 2026-2030)
        $planejamento = Planejamento::firstOrCreate(
            ['nome' => 'Plano Estratégico Institucional PGE-MA (Ciclo 2026–2030)'],
            [
                'id' => (string) Str::uuid(),
                'data_inicio' => '2026-01-01 00:00:00',
                'data_fim' => '2030-12-31 23:59:59',
                'missao' => 'Promover a defesa judicial e a consultoria jurídica do Estado do Maranhão, orientando a legalidade dos atos administrativos e defendendo o interesse público com integridade e eficiência.',
                'visao' => 'Ser uma instituição de excelência e referência nacional em advocacia pública, inovação e sustentabilidade institucional até 2030.',
                'valores' => [
                    'Legalidade e Integridade',
                    'Excelência Técnica e Resolutividade',
                    'Inovação e Transformação Digital',
                    'Sustentabilidade e Responsabilidade Social',
                    'Valorização das Pessoas'
                ],
                'resultados_institucionais' => [
                    'Arrecadação fiscal e recuperação da dívida ativa otimizadas',
                    'Eficiência administrativa e redução contínua de despesas correntes',
                    'Modernização digital com sustentabilidade e governança integrada'
                ],
                'entidade_id' => $entidade->id,
                'unidade_id' => $unidadeRaiz->id,
            ]
        );
        $this->command->info("Planejamento Institucional criado.");

        // 7. Cadastrar e Sincronizar Objetivos Estratégicos e KRs (Key Results / OKRs)
        $krsList = $data['overview']['krs'] ?? [];
        $objetivosMap = [];
        $objetivosAtivosIds = [];
        $seq = 1;

        foreach ($krsList as $kr) {
            $eixoTxt = $kr['eixo'] ?? '';
            $objTxt = $kr['objetivo'] ?? '';
            $krCod = trim($kr['kr_codigo'] ?? '');
            $krFull = $kr['kr_texto_completo'] ?? $krCod;

            // Extrair código do eixo (ex: E1)
            preg_match('/^Eixo Estratégico (\d+)/i', $eixoTxt, $m);
            $eixoCod = isset($m[1]) ? "E{$m[1]}" : 'E1';
            $eixoId = $eixosMap[$eixoCod] ?? (count($eixosMap) ? reset($eixosMap) : null);

            if (!$eixoId) continue;

            // Chave única para o Objetivo Pai
            $objKey = "{$eixoCod}_{$objTxt}";
            if (!isset($objetivosMap[$objKey])) {
                $objPai = PlanejamentoObjetivo::withTrashed()
                    ->where('planejamento_id', $planejamento->id)
                    ->where('integra_okr', 0)
                    ->where('nome', $objTxt)
                    ->first();

                if ($objPai) {
                    $objPai->restore();
                    $objPai->update([
                        'fundamentacao' => "Objetivo Estratégico vinculado ao {$eixoCod}",
                        'sequencia' => $seq++,
                        'eixo_tematico_id' => $eixoId,
                        'objetivo_pai_id' => null,
                    ]);
                } else {
                    $objPai = PlanejamentoObjetivo::create([
                        'id' => (string) Str::uuid(),
                        'planejamento_id' => $planejamento->id,
                        'nome' => $objTxt,
                        'fundamentacao' => "Objetivo Estratégico vinculado ao {$eixoCod}",
                        'sequencia' => $seq++,
                        'eixo_tematico_id' => $eixoId,
                        'integra_okr' => 0,
                        'objetivo_pai_id' => null,
                    ]);
                }
                $objetivosMap[$objKey] = $objPai->id;
                $objetivosAtivosIds[] = $objPai->id;
            }

            // Localizar ou criar o KR como Objetivo Filho com integra_okr = 1
            $fundamentacaoKr = "Key Result [{$krCod}] da PGE-MA";
            $krObj = PlanejamentoObjetivo::withTrashed()
                ->where('planejamento_id', $planejamento->id)
                ->where('integra_okr', 1)
                ->where(function ($q) use ($fundamentacaoKr, $krCod, $krFull) {
                    $q->where('fundamentacao', $fundamentacaoKr)
                      ->orWhere('nome', 'like', "[{$krCod}]%")
                      ->orWhere('nome', $krFull);
                })
                ->first();

            if ($krObj) {
                $krObj->restore();
                $krObj->update([
                    'nome' => $krFull,
                    'fundamentacao' => $fundamentacaoKr,
                    'sequencia' => $seq++,
                    'eixo_tematico_id' => $eixoId,
                    'integra_okr' => 1,
                    'objetivo_pai_id' => $objetivosMap[$objKey],
                ]);
            } else {
                $krObj = PlanejamentoObjetivo::create([
                    'id' => (string) Str::uuid(),
                    'planejamento_id' => $planejamento->id,
                    'nome' => $krFull,
                    'fundamentacao' => $fundamentacaoKr,
                    'sequencia' => $seq++,
                    'eixo_tematico_id' => $eixoId,
                    'integra_okr' => 1,
                    'objetivo_pai_id' => $objetivosMap[$objKey],
                ]);
            }
            $objetivosAtivosIds[] = $krObj->id;
        }

        // Remover KRs/Objetivos obsoletos que saíram do SGPE (ex: E7.O3.KR2 unificado em E9.O2.KR1)
        if (!empty($objetivosAtivosIds)) {
            $obsoletosIds = PlanejamentoObjetivo::withTrashed()
                ->where('planejamento_id', $planejamento->id)
                ->whereNotIn('id', $objetivosAtivosIds)
                ->pluck('id')
                ->toArray();

            if (!empty($obsoletosIds)) {
                PlanoEntregaEntregaObjetivo::withTrashed()
                    ->whereIn('planejamento_objetivo_id', $obsoletosIds)
                    ->forceDelete();

                PlanejamentoObjetivo::withTrashed()
                    ->whereIn('id', $obsoletosIds)
                    ->whereNotNull('objetivo_pai_id')
                    ->forceDelete();

                PlanejamentoObjetivo::withTrashed()
                    ->whereIn('id', $obsoletosIds)
                    ->whereNull('objetivo_pai_id')
                    ->forceDelete();

                $this->command->info(count($obsoletosIds) . " Objetivos/KRs obsoletos removidos.");
            }
        }
        $this->command->info(count($krsList) . " KRs e Objetivos Estratégicos sincronizados.");

        // 8. Cadastrar Grupos de Trabalho (GTs) como Projetos Estruturantes
        $tipoProjeto = TipoProjeto::firstOrCreate(
            ['nome' => 'Projeto Estruturante'],
            [
                'id' => (string) Str::uuid(),
                'icone' => 'bi bi-kanban',
                'cor' => '#0284C7'
            ]
        );

        $gtsList = $data['gts'] ?? [];
        foreach ($gtsList as $gt) {
            $codigo = trim($gt['codigo'] ?? '');
            $titulo = trim($gt['titulo'] ?? '');
            $objetivo = trim($gt['objetivo'] ?? '');
            $statusGt = trim($gt['status'] ?? '');

            // Mapear status do GT para enum do Petrvs
            $statusProjeto = 'PLANEJADO';
            if (stripos($statusGt, 'Execução') !== false || stripos($statusGt, 'Implantação') !== false) {
                $statusProjeto = 'INICIADO';
            } elseif (stripos($statusGt, '100%') !== false || stripos($statusGt, 'Concluído') !== false) {
                $statusProjeto = 'CONCLUIDO';
            }

            $nomeProjeto = mb_substr("[{$codigo}] {$titulo}", 0, 250);
            $projetoObj = Projeto::withTrashed()->where('nome', 'like', "[{$codigo}]%")->first();
            if ($projetoObj) {
                $projetoObj->restore();
                $projetoObj->update([
                    'nome' => $nomeProjeto,
                    'descricao' => mb_substr($objetivo, 0, 250),
                    'finalidade' => mb_substr("Metas e Entregáveis do {$codigo} da PGE-MA", 0, 250),
                    'status' => $statusProjeto,
                    'tipo_projeto_id' => $tipoProjeto->id,
                ]);
            } else {
                Projeto::create([
                    'id' => (string) Str::uuid(),
                    'nome' => $nomeProjeto,
                    'descricao' => mb_substr($objetivo, 0, 250),
                    'finalidade' => mb_substr("Metas e Entregáveis do {$codigo} da PGE-MA", 0, 250),
                    'status' => $statusProjeto,
                    'data_inicio' => '2026-01-01 00:00:00',
                    'data_fim' => '2028-12-31 23:59:59',
                    'duracao' => 730,
                    'progresso' => 0,
                    'calcula_custos' => 0,
                    'tempo_corrido' => 1,
                    'usa_horas' => 0,
                    'usa_baseline' => 1,
                    'calcula_intervalo' => 0,
                    'agrupador' => 0,
                    'soma_progresso_filhos' => 1,
                    'aloca_proprios_recursos' => 0,
                    'soma_recusos_alocados_filhos' => 0,
                    'custos_proprios' => 0,
                    'soma_custos_filhos' => 0,
                    'custo' => 0,
                    'tipo_projeto_id' => $tipoProjeto->id,
                ]);
            }
        }
        $this->command->info(count($gtsList) . " Projetos Estruturantes (GTs) sincronizados.");

        // 9. Sincronizar tabelas auxiliares sgpe_iniciativas e sgpe_projetos (Painéis, Home V2 e Corregedoria)
        DB::statement("CREATE TABLE IF NOT EXISTS `sgpe_iniciativas` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `sigla_unidade` varchar(50) NOT NULL,
            `ordem` int(11) DEFAULT 1,
            `eixo` varchar(255) DEFAULT NULL,
            `objetivo` varchar(255) DEFAULT NULL,
            `kr_codigo` varchar(50) NOT NULL,
            `kr_texto_completo` text DEFAULT NULL,
            `acao` text NOT NULL,
            `subacao` text DEFAULT NULL,
            `responsaveis` text DEFAULT NULL,
            `prazo` varchar(100) DEFAULT NULL,
            `pilar_3ps` varchar(100) DEFAULT NULL,
            `papel_unidade` varchar(50) DEFAULT NULL,
            `unidades_parceiras` text DEFAULT NULL,
            `status_execucao` varchar(100) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_sigla_unidade` (`sigla_unidade`),
            KEY `idx_kr_codigo` (`kr_codigo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        DB::statement("CREATE TABLE IF NOT EXISTS `sgpe_projetos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `codigo` varchar(20) NOT NULL,
            `titulo` varchar(255) NOT NULL,
            `tipo` varchar(100) DEFAULT NULL,
            `krs_vinculados` text DEFAULT NULL,
            `lider` varchar(255) DEFAULT NULL,
            `unidades_participantes` text DEFAULT NULL,
            `status` varchar(100) DEFAULT NULL,
            `prazo` varchar(100) DEFAULT NULL,
            `objetivo` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_codigo` (`codigo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        DB::table('sgpe_iniciativas')->truncate();
        $acoesInsert = [];
        foreach ($data['acoes'] ?? [] as $acao) {
            $acoesInsert[] = [
                'sigla_unidade' => trim($acao['sigla_unidade'] ?? ''),
                'ordem' => (int) ($acao['ordem'] ?? 1),
                'eixo' => $acao['eixo'] ?? null,
                'objetivo' => $acao['objetivo'] ?? null,
                'kr_codigo' => trim($acao['kr_codigo'] ?? ''),
                'kr_texto_completo' => $acao['kr_texto_completo'] ?? null,
                'acao' => $acao['acao'] ?? '',
                'subacao' => $acao['subacao'] ?? null,
                'responsaveis' => $acao['responsaveis'] ?? null,
                'prazo' => $acao['prazo'] ?? null,
                'pilar_3ps' => $acao['pilar_3ps'] ?? null,
                'papel_unidade' => $acao['papel_unidade'] ?? null,
                'unidades_parceiras' => is_array($acao['unidades_parceiras'] ?? null)
                    ? implode(',', $acao['unidades_parceiras'])
                    : ($acao['unidades_parceiras'] ?? null),
                'status_execucao' => $acao['status_execucao'] ?? null,
            ];
            if (count($acoesInsert) >= 250) {
                DB::table('sgpe_iniciativas')->insert($acoesInsert);
                $acoesInsert = [];
            }
        }
        if (!empty($acoesInsert)) {
            DB::table('sgpe_iniciativas')->insert($acoesInsert);
        }

        DB::table('sgpe_projetos')->truncate();
        $gtsInsert = [];
        foreach ($gtsList as $gt) {
            $gtsInsert[] = [
                'codigo' => trim($gt['codigo'] ?? ''),
                'titulo' => trim($gt['titulo'] ?? ''),
                'tipo' => $gt['tipo'] ?? null,
                'krs_vinculados' => is_array($gt['krs_vinculados'] ?? null)
                    ? implode(', ', $gt['krs_vinculados'])
                    : ($gt['krs_vinculados'] ?? null),
                'lider' => $gt['lider'] ?? null,
                'unidades_participantes' => is_array($gt['unidades_participantes'] ?? null)
                    ? implode(', ', $gt['unidades_participantes'])
                    : ($gt['unidades_participantes'] ?? null),
                'status' => $gt['status'] ?? null,
                'prazo' => $gt['prazo'] ?? null,
                'objetivo' => $gt['objetivo'] ?? null,
            ];
        }
        if (!empty($gtsInsert)) {
            DB::table('sgpe_projetos')->insert($gtsInsert);
        }
        $this->command->info("Tabelas sgpe_iniciativas (" . count($data['acoes'] ?? []) . ") e sgpe_projetos (" . count($gtsList) . ") sincronizadas.");

        $this->command->info("=== Semeamento PGE-MA Concluído com Sucesso! ===");
    }
}
