<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\Entidade;
use App\Models\Unidade;
use App\Models\Usuario;
use App\Models\UnidadeIntegrante;
use App\Models\UnidadeIntegranteAtribuicao;
use App\Models\Programa;
use App\Models\Planejamento;
use App\Models\PlanejamentoObjetivo;
use App\Models\PlanoEntrega;
use App\Models\PlanoEntregaEntrega;
use App\Models\PlanoEntregaEntregaObjetivo;
use App\Models\Entrega;

class PgemaPlanosAcaoSeeder extends Seeder
{
    public function run()
    {
        $jsonPath = __DIR__ . '/data/dados_sgpe.json';
        if (!file_exists($jsonPath)) {
            $this->command->error("Arquivo de dados do SGPE não encontrado em {$jsonPath}");
            return;
        }

        $this->command->info("Iniciando carga de Planos de Ação Setoriais da PGE-MA...");
        $data = json_decode(file_get_contents($jsonPath), true);
        if (!$data) {
            $this->command->error("Falha ao ler JSON do SGPE.");
            return;
        }

        $now = now();

        // 1. Obter Entidade e Unidade Raiz da PGE-MA
        $entidade = Entidade::where('sigla', 'PGE-MA')->first() ?? Entidade::first();
        $unidadeRaiz = Unidade::where('sigla', 'PGE-MA')->first() ?? Unidade::first();
        $planejamento = Planejamento::where('nome', 'like', '%PGE-MA%')->first() ?? Planejamento::first();

        if (!$entidade || !$unidadeRaiz || !$planejamento) {
            $this->command->error("Entidade, Unidade Raiz ou Planejamento Institucional não encontrados.");
            return;
        }

        // 2. Garantir o Programa Institucional de Gestão e Ação Estratégica da PGE-MA
        $programa = Programa::firstOrCreate(
            ['nome' => 'Programa de Gestão e Ação Estratégica PGE-MA (Ciclo 2026–2030)'],
            [
                'id' => (string) Str::uuid(),
                'normativa' => 'Resolução PGE-MA nº 01/2026 - Gestão Estratégica e Planos de Ação Setoriais',
                'prazo_max_plano_entrega' => 1825,
                'termo_obrigatorio' => 0,
                'config' => null,
                'data_inicio' => '2026-01-01 00:00:00',
                'data_fim' => '2030-12-31 23:59:59',
                'periodicidade_consolidacao' => 'MENSAL',
                'periodicidade_valor' => 1,
                'dias_tolerancia_consolidacao' => 10,
                'dias_tolerancia_avaliacao' => 20,
                'dias_tolerancia_recurso_avaliacao' => 20,
                'nota_padrao_avaliacao' => 'Planejado',
                'checklist_avaliacao_entregas_plano_entrega' => '[]',
                'checklist_avaliacao_entregas_plano_trabalho' => '[]',
                'plano_trabalho_criterios_avaliacao' => '[]',
                'registra_comparecimento' => 0,
                'plano_trabalho_assinatura_participante' => 0,
                'plano_trabalho_assinatura_gestor_lotacao' => 0,
                'plano_trabalho_assinatura_gestor_unidade' => 0,
                'plano_trabalho_assinatura_gestor_entidade' => 0,
                'tipo_avaliacao_plano_trabalho_id' => '005b3fbd-c457-4a50-b28e-de17da2d73a5',
                'tipo_avaliacao_plano_entrega_id' => 'b0db190d-823d-4222-bc92-abff634f5390',
                'unidade_id' => $unidadeRaiz->id,
                'template_tcr_id' => 'ef1c106c-3534-46d2-9519-cc69d5cf9dad',
                'tipo_documento_tcr_id' => '48bc6f30-a634-4a21-9717-6fe0dc0d4f2a',
                'documento_id' => null,
            ]
        );
        $this->command->info("Programa Institucional assegurado: {$programa->nome}");

        // 3. Obter ou criar Entrega Padrão (Catálogo de Metas)
        $entregaCatalogo = Entrega::firstOrCreate(
            ['id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'],
            [
                'nome' => 'Entrega Padrão',
                'descricao' => 'Meta e entrega padrão de planos de ação setorial',
                'tipo_indicador' => 'QUANTIDADE',
                'unidade_id' => $unidadeRaiz->id,
            ]
        );

        // 4. Mapear KRs / Objetivos Estratégicos existentes no banco
        $objetivos = PlanejamentoObjetivo::where('planejamento_id', $planejamento->id)->get();
        $krMap = [];
        foreach ($objetivos as $obj) {
            if (preg_match('/\[([A-Z0-9\.]+)\]/i', $obj->nome, $m)) {
                $krMap[strtoupper($m[1])] = $obj->id;
            }
            if (preg_match('/\[([A-Z0-9\.]+)\]/i', $obj->fundamentacao ?? '', $m)) {
                $krMap[strtoupper($m[1])] = $obj->id;
            }
        }
        $this->command->info(count($krMap) . " KRs mapeados do Planejamento Institucional.");

        // 5. Mapear Unidades cadastradas no banco
        $unidadesDb = Unidade::all()->keyBy('sigla');

        // 6. Agrupar Ações do SGPE por sigla_unidade
        $acoesPorUnidade = [];
        foreach ($data['acoes'] ?? [] as $acao) {
            $sigla = trim($acao['sigla_unidade'] ?? '');
            if ($sigla) {
                $acoesPorUnidade[$sigla][] = $acao;
            }
        }

        $perfilUnidadeId = 'eccbc87e-4b5c-e2fe-2830-8fd9f2a7baf3'; // Perfil Unidade
        $totalGestoresCriados = 0;
        $totalPlanosCriados = 0;
        $totalEntregasCriadas = 0;
        $totalEntregasRemovidas = 0;

        $unidadesOverview = $data['overview']['unidades'] ?? [];

        $this->command->info("Sincronizando gestores, planos setoriais e ações...");
        PlanoEntrega::withoutEvents(function () use (
            &$totalGestoresCriados,
            &$totalPlanosCriados,
            &$totalEntregasCriadas,
            &$totalEntregasRemovidas,
            $unidadesOverview,
            $unidadesDb,
            $perfilUnidadeId,
            $acoesPorUnidade,
            $planejamento,
            $programa,
            $entregaCatalogo,
            $krMap
        ) {
            PlanoEntregaEntrega::withoutEvents(function () use (
                &$totalGestoresCriados,
                &$totalPlanosCriados,
                &$totalEntregasCriadas,
                &$totalEntregasRemovidas,
                $unidadesOverview,
                $unidadesDb,
                $perfilUnidadeId,
                $acoesPorUnidade,
                $planejamento,
                $programa,
                $entregaCatalogo,
                $krMap
            ) {
                $todasEntregasAtivasIds = [];

                foreach ($unidadesOverview as $index => $uMeta) {
                    $sigla = trim($uMeta['sigla'] ?? '');
                    if (empty($sigla) || !isset($unidadesDb[$sigla])) {
                        continue;
                    }

                    $unidade = $unidadesDb[$sigla];
                    $responsavelRaw = trim($uMeta['responsavel'] ?? '');

                    // Normalizar nome do gestor
                    $nomeGestor = $this->normalizarNomeGestor($responsavelRaw, $sigla, $uMeta['nome'] ?? $sigla);
                    $siglaLower = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $sigla));
                    $emailGestor = "gestor.{$siglaLower}@pge.ma.gov.br";
                    $cpfGestor = sprintf('999%08d', $index + 1);

                    // 7. Criar ou localizar o Agente Público Gestor da Unidade
                    $usuario = Usuario::firstOrCreate(
                        ['email' => $emailGestor],
                        [
                            'id' => (string) Str::uuid(),
                            'nome' => $nomeGestor,
                            'cpf' => $cpfGestor,
                            'matricula' => sprintf('PGE-%05d', $index + 100),
                            'apelido' => explode(' ', $nomeGestor)[0],
                            'perfil_id' => $perfilUnidadeId,
                            'situacao_funcional' => 'ATIVO_PERMANENTE',
                            'situacao_siape' => 'ATIVO',
                            'usuario_externo' => 0,
                            'is_admin' => 0,
                            'participa_pgd' => 'sim',
                            'password' => bcrypt('123456'),
                        ]
                    );
                    $totalGestoresCriados++;

                    // 8. Vincular Agente Público à Unidade como GESTOR e LOTADO
                    $unidadeIntegrante = UnidadeIntegrante::firstOrCreate(
                        [
                            'unidade_id' => $unidade->id,
                            'usuario_id' => $usuario->id,
                        ],
                        [
                            'id' => (string) Str::uuid(),
                        ]
                    );

                    foreach (['GESTOR', 'LOTADO', 'HOMOLOGADOR_PLANO_ENTREGA', 'AVALIADOR_PLANO_ENTREGA'] as $atrib) {
                        UnidadeIntegranteAtribuicao::firstOrCreate([
                            'unidade_integrante_id' => $unidadeIntegrante->id,
                            'atribuicao' => $atrib,
                        ], [
                            'id' => (string) Str::uuid(),
                        ]);
                    }

                    // Se a unidade não tiver ações no SGPE, passa para a próxima
                    if (!isset($acoesPorUnidade[$sigla])) {
                        continue;
                    }

                    $acoesDaUnidade = $acoesPorUnidade[$sigla];
                    $processoSei = trim($uMeta['processo_sei'] ?? '');
                    $temSei = !empty($processoSei) && stripos($processoSei, 'Verificar') === false;

                    // 9. Criar ou localizar o Plano de Ação Setorial (Plano de Entrega)
                    $nomePlano = "Plano de Ação Setorial (2026–2030) - {$sigla}";
                    $planoEntrega = PlanoEntrega::withTrashed()
                        ->where('unidade_id', $unidade->id)
                        ->where('planejamento_id', $planejamento->id)
                        ->first();

                    if ($planoEntrega) {
                        $planoEntrega->restore();
                        $planoEntrega->update([
                            'nome' => $nomePlano,
                            'status' => 'ATIVO',
                            'programa_id' => $programa->id,
                        ]);
                    } else {
                        $proximoNumero = app(\App\Services\TenantSequenceService::class)->nextNumber(\App\Enums\SequenceType::PLANO_ENTREGA);
                        $planoEntrega = PlanoEntrega::create([
                            'id' => (string) Str::uuid(),
                            'unidade_id' => $unidade->id,
                            'planejamento_id' => $planejamento->id,
                            'numero' => $proximoNumero,
                            'nome' => $nomePlano,
                            'data_inicio' => '2026-01-01 00:00:00',
                            'data_fim' => '2030-12-31 23:59:59',
                            'status' => 'ATIVO',
                            'programa_id' => $programa->id,
                            'criacao_usuario_id' => $usuario->id,
                        ]);
                    }
                    $totalPlanosCriados++;

                    $objetivosPorEntrega = [];

                    // 10. Criar ou atualizar as Entregas vinculadas às Ações do SGPE
                    foreach ($acoesDaUnidade as $idxAcao => $acao) {
                        $tituloAcao = mb_substr(trim($acao['acao'] ?? "Ação " . ($idxAcao + 1)), 0, 250);
                        $subacao = trim($acao['subacao'] ?? $tituloAcao);
                        $prazo = trim($acao['prazo'] ?? 'Ciclo 2026–2030');
                        $pilar = trim($acao['pilar_3ps'] ?? 'Estratégico');
                        $papel = trim($acao['papel_unidade'] ?? 'Líder');
                        $parceiras = is_array($acao['unidades_parceiras'] ?? null)
                            ? implode(', ', $acao['unidades_parceiras'])
                            : trim($acao['unidades_parceiras'] ?? '');
                        $statusExec = trim($acao['status_execucao'] ?? 'Planejado');
                        $krCodigo = strtoupper(trim($acao['kr_codigo'] ?? ''));

                        // Progresso e Realizado
                        $progressoRealizado = 0.00;
                        $realizado = 0;
                        if (stripos($statusExec, 'Concluído') !== false || stripos($statusExec, '100%') !== false) {
                            $progressoRealizado = 100.00;
                            $realizado = 100;
                        } elseif (stripos($statusExec, 'Execução') !== false || stripos($statusExec, 'Andamento') !== false) {
                            $progressoRealizado = 50.00;
                            $realizado = 50;
                        }

                        $metaDesc = "Prazo: {$prazo} | Papel: {$papel}";
                        if ($parceiras) {
                            $metaDesc .= " | Coexecutores: {$parceiras}";
                        }
                        if ($temSei) {
                            $metaDesc .= " | Processo SEI: {$processoSei}";
                        }

                        // Etiquetas formatadas
                        $etiquetas = [
                            ['key' => 'pilar', 'value' => $pilar, 'color' => '#047857', 'icon' => 'bi bi-tag'],
                            ['key' => 'status', 'value' => $statusExec, 'color' => '#1E3A8A', 'icon' => 'bi bi-check2-circle'],
                            ['key' => 'prazo', 'value' => $prazo, 'color' => '#B45309', 'icon' => 'bi bi-clock']
                        ];

                        $entrega = PlanoEntregaEntrega::withTrashed()
                            ->where('plano_entrega_id', $planoEntrega->id)
                            ->where('descricao', $tituloAcao)
                            ->first();

                        if ($entrega) {
                            $entrega->restore();
                            $entrega->update([
                                'unidade_id' => $unidade->id,
                                'entrega_id' => $entregaCatalogo->id,
                                'descricao_entrega' => $subacao,
                                'descricao_meta' => $metaDesc,
                                'destinatario' => 'PGE-MA e Sociedade Maranhense',
                                'meta' => ['quantitativo' => 100],
                                'realizado' => ['quantitativo' => $realizado],
                                'progresso_esperado' => 100.00,
                                'progresso_realizado' => $progressoRealizado,
                                'homologado' => 1,
                                'etiquetas' => $etiquetas,
                            ]);
                        } else {
                            $entrega = PlanoEntregaEntrega::create([
                                'id' => (string) Str::uuid(),
                                'plano_entrega_id' => $planoEntrega->id,
                                'descricao' => $tituloAcao,
                                'unidade_id' => $unidade->id,
                                'entrega_id' => $entregaCatalogo->id,
                                'descricao_entrega' => $subacao,
                                'descricao_meta' => $metaDesc,
                                'destinatario' => 'PGE-MA e Sociedade Maranhense',
                                'meta' => ['quantitativo' => 100],
                                'realizado' => ['quantitativo' => $realizado],
                                'progresso_esperado' => 100.00,
                                'progresso_realizado' => $progressoRealizado,
                                'homologado' => 1,
                                'data_inicio' => '2026-01-01 00:00:00',
                                'data_fim' => '2030-12-31 23:59:59',
                                'etiquetas' => $etiquetas,
                            ]);
                        }

                        $todasEntregasAtivasIds[] = $entrega->id;
                        $totalEntregasCriadas++;

                        // 11. Vincular Entrega ao KR correspondente
                        if ($krCodigo && isset($krMap[$krCodigo])) {
                            $objId = $krMap[$krCodigo];
                            $objetivosPorEntrega[$entrega->id][] = $objId;

                            $vinculoObj = PlanoEntregaEntregaObjetivo::withTrashed()
                                ->where('entrega_id', $entrega->id)
                                ->where('planejamento_objetivo_id', $objId)
                                ->first();

                            if ($vinculoObj) {
                                $vinculoObj->restore();
                            } else {
                                PlanoEntregaEntregaObjetivo::create([
                                    'id' => (string) Str::uuid(),
                                    'entrega_id' => $entrega->id,
                                    'planejamento_objetivo_id' => $objId,
                                ]);
                            }
                        }
                    }

                    // Limpar vínculos de KRs antigos nas entregas ativas desta unidade
                    foreach ($objetivosPorEntrega as $entId => $objIdsAtivos) {
                        PlanoEntregaEntregaObjetivo::withTrashed()
                            ->where('entrega_id', $entId)
                            ->whereNotIn('planejamento_objetivo_id', array_unique($objIdsAtivos))
                            ->forceDelete();
                    }
                }

                // 12. Expurgar entregas obsoletas (heranças indevidas removidas do SGPE) que não possuem plano de trabalho vinculado
                $todasEntregasAtivasIds = array_values(array_unique($todasEntregasAtivasIds));
                $planosDoPlanejamentoIds = PlanoEntrega::withTrashed()
                    ->where('planejamento_id', $planejamento->id)
                    ->pluck('id')
                    ->toArray();

                if (!empty($planosDoPlanejamentoIds) && !empty($todasEntregasAtivasIds)) {
                    $entregasComPtIds = DB::table('planos_trabalhos_entregas')
                        ->whereNotNull('plano_entrega_entrega_id')
                        ->whereNull('deleted_at')
                        ->pluck('plano_entrega_entrega_id')
                        ->toArray();

                    $entregasObsoletasIds = PlanoEntregaEntrega::withTrashed()
                        ->whereIn('plano_entrega_id', $planosDoPlanejamentoIds)
                        ->whereNotIn('id', $todasEntregasAtivasIds)
                        ->whereNotIn('id', $entregasComPtIds)
                        ->pluck('id')
                        ->toArray();

                    if (!empty($entregasObsoletasIds)) {
                        PlanoEntregaEntregaObjetivo::withTrashed()
                            ->whereIn('entrega_id', $entregasObsoletasIds)
                            ->forceDelete();

                        DB::table('planos_entregas_entregas_progressos')
                            ->whereIn('plano_entrega_entrega_id', $entregasObsoletasIds)
                            ->delete();

                        $totalEntregasRemovidas = PlanoEntregaEntrega::withTrashed()
                            ->whereIn('id', $entregasObsoletasIds)
                            ->forceDelete();
                    }
                }
            });
        });

        $this->command->info("=== CARGA E SANEAMENTO CONCLUÍDOS COM SUCESSO! ===");
        $this->command->info("• Agentes Públicos Gestores: {$totalGestoresCriados}");
        $this->command->info("• Planos de Ação Setoriais Sincronizados: {$totalPlanosCriados}");
        $this->command->info("• Ações / Entregas Estratégicas Sincronizadas: {$totalEntregasCriadas}");
        $this->command->info("• Entregas Obsoletas Expurgadas: {$totalEntregasRemovidas}");
    }

    /**
     * Limpa e formata o nome do responsável
     */
    private function normalizarNomeGestor(string $raw, string $sigla, string $nomeUnidade): string
    {
        if (empty($raw) || stripos($raw, 'Chefia') !== false || stripos($raw, 'Presidência') !== false || stripos($raw, 'Direção') !== false || stripos($raw, 'Procurador(a)') !== false || stripos($raw, 'Corpo de') !== false) {
            return "Gestor(a) - {$sigla}";
        }

        // Se tiver '/', pegar o primeiro
        if (strpos($raw, '/') !== false) {
            $parts = explode('/', $raw);
            $raw = trim($parts[0]);
        }

        // Remover observações entre parênteses
        $raw = preg_replace('/\(.*?\)/', '', $raw);
        $raw = trim($raw);

        return $raw ?: "Gestor(a) - {$sigla}";
    }
}
