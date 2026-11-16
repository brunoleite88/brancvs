<?php

declare(strict_types=1);

namespace App\V2\PainelGerencial\Cockpit;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provedor de dados integrados do Cockpit Executivo do Gabinete.
 * Consome em tempo real as tabelas operacionais do tenant sem valores simulados.
 */
class CockpitGabineteDataProvider
{
    public function __construct(
        private readonly CockpitConfigService $configService,
    ) {}

    public function getDados(array $filtros = []): array
    {
        $config = $this->configService->getConfig();
        $regras = $config['regras_farol'];
        $hoje = now();

        $unidades = $this->carregarUnidades();
        $gestoresPorUnidade = $this->carregarGestoresPorUnidade();

        $statusPermitidos = $this->resolverStatusPlanos(
            $filtros['status_planos'] ?? ($config['opcoes']['status_planos_padrao'] ?? 'VALIDOS'),
            (bool) $regras['incluir_planos_nao_ativos']
        );

        $planosQuery = DB::connection('tenant')->table('planos_entregas')
            ->whereNull('deleted_at')
            ->whereIn('status', $statusPermitidos);

        if (!empty($filtros['data_inicio'])) {
            $dtIni = Carbon::parse($filtros['data_inicio'])->startOfDay();
            $planosQuery->where(function ($q) use ($dtIni) {
                $q->whereNull('data_fim')->orWhere('data_fim', '>=', $dtIni);
            });
        }
        if (!empty($filtros['data_fim'])) {
            $dtFim = Carbon::parse($filtros['data_fim'])->endOfDay();
            $planosQuery->where('data_inicio', '<=', $dtFim);
        }

        $planos = $planosQuery->orderByRaw("CASE status WHEN 'ATIVO' THEN 1 WHEN 'HOMOLOGANDO' THEN 2 WHEN 'CONCLUIDO' THEN 3 ELSE 4 END")
            ->orderByDesc('data_inicio')
            ->get(['id', 'numero', 'nome', 'status', 'unidade_id', 'data_inicio', 'data_fim']);

        $planosPorUnidade = [];
        $planoIds = [];
        foreach ($planos as $p) {
            $planosPorUnidade[$p->unidade_id][] = $p;
            $planoIds[] = $p->id;
        }

        $entregas = empty($planoIds)
            ? collect()
            : DB::connection('tenant')->table('planos_entregas_entregas')
                ->whereNull('deleted_at')
                ->whereIn('plano_entrega_id', $planoIds)
                ->get([
                    'id',
                    'plano_entrega_id',
                    'unidade_id',
                    'descricao',
                    'descricao_entrega',
                    'progresso_esperado',
                    'progresso_realizado',
                    'homologado',
                    'data_inicio',
                    'data_fim',
                    'etiquetas',
                ]);

        $planoParaUnidade = [];
        foreach ($planos as $p) {
            $planoParaUnidade[$p->id] = $p->unidade_id;
        }

        $dadosIntegrados = $this->carregarIntegracoesOperacionais($entregas, $hoje);

        $pilaresContagem = [
            '1º P: Prazo' => 0,
            '2º P: Presença nos Tribunais' => 0,
            '3º P: Padronização e Prevenção' => 0,
            'Outros Eixos / Governança' => 0,
        ];
        $faixasPrazoGlobais = [
            'curto_prazo' => 0,
            'medio_prazo' => 0,
            'estruturante' => 0,
        ];

        $entregasPorUnidade = [];
        $somaProgressoGlobal = 0.0;
        $totalAtrasadasGlobal = 0;
        $totalVencendoGlobal = 0;
        $totalConcluidasGlobal = 0;

        $limiteAlerta = $hoje->copy()->addDays((int) $regras['dias_alerta_vencimento']);
        $diasCurto = (int) $regras['dias_curto_prazo'];
        $diasMedio = (int) $regras['dias_medio_prazo'];
        $usarTagSgpePrazo = (bool) $regras['contabilizar_etiquetas_sgpe_prazo'];

        foreach ($entregas as $e) {
            $uId = $planoParaUnidade[$e->plano_entrega_id] ?? $e->unidade_id;
            $entregasPorUnidade[$uId][] = $e;

            $prog = (float) ($e->progresso_realizado ?? 0);
            $somaProgressoGlobal += $prog;
            if ($prog >= 100.0) {
                $totalConcluidasGlobal++;
            }

            $dtFim = $e->data_fim ? Carbon::parse($e->data_fim) : null;
            if ($dtFim && $prog < 100.0) {
                if ($dtFim->lt($hoje)) {
                    $totalAtrasadasGlobal++;
                } elseif ($dtFim->lte($limiteAlerta)) {
                    $totalVencendoGlobal++;
                }
            }

            $tags = json_decode($e->etiquetas ?? '[]', true);
            $pilarTag = null;
            $prazoTag = null;
            if (is_array($tags)) {
                foreach ($tags as $tag) {
                    $k = $tag['key'] ?? '';
                    $v = trim((string) ($tag['value'] ?? ''));
                    if ($k === 'pilar' && $v !== '') {
                        $pilarTag = $v;
                    } elseif ($k === 'prazo' && $v !== '') {
                        $prazoTag = $v;
                    }
                }
            }

            if ($pilarTag && isset($pilaresContagem[$pilarTag])) {
                $pilaresContagem[$pilarTag]++;
            } else {
                $pilaresContagem['Outros Eixos / Governança']++;
            }

            $faixa = $this->classificarFaixaPrazo($dtFim, $prazoTag, $hoje, $diasCurto, $diasMedio, $usarTagSgpePrazo);
            $faixasPrazoGlobais[$faixa]++;
        }

        $totalEntregasGlobal = $entregas->count();
        $pilaresDetalhados = [];
        foreach ($pilaresContagem as $nome => $qtd) {
            $pilaresDetalhados[] = [
                'nome' => $nome,
                'total' => $qtd,
                'percentual' => $totalEntregasGlobal > 0 ? round(($qtd / $totalEntregasGlobal) * 100, 1) : 0.0,
            ];
        }

        $macroMapa = $this->montarMapaMacroAreas($config['macro_areas']);
        $consolidadoras = array_map('strtoupper', $regras['unidades_consolidadoras'] ?? ['PGE-MA']);

        $resumoMacro = [];
        foreach ($config['macro_areas'] as $ma) {
            $resumoMacro[$ma['nome']] = [
                'macro_area' => $ma['nome'],
                'total_unidades' => 0,
                'total_entregas' => 0,
                'soma_progresso' => 0.0,
                'verde' => 0,
                'amarelo' => 0,
                'vermelho' => 0,
                'repactuacoes_pendentes' => 0,
                'planos_individuais' => 0,
                'progresso_medio' => 0.0,
            ];
        }

        $unidadesDetalhes = [];
        $farolVerdeQtd = 0;
        $farolAmareloQtd = 0;
        $farolVermelhoQtd = 0;
        $unidadesComPlano = 0;
        $unidadesAlvoCobertura = 0;

        foreach ($unidades as $u) {
            $siglaUpper = strtoupper(trim((string) $u->sigla));
            $macro = $macroMapa[$siglaUpper] ?? 'Outros Setores';
            $ehConsolidadora = in_array($siglaUpper, $consolidadoras, true);

            $listaPlanos = $planosPorUnidade[$u->id] ?? [];
            $planoPrincipal = $listaPlanos[0] ?? null;
            $listaEntregas = $entregasPorUnidade[$u->id] ?? [];
            $totalEntregas = count($listaEntregas);

            if (!$ehConsolidadora) {
                $unidadesAlvoCobertura++;
                if ($planoPrincipal) {
                    $unidadesComPlano++;
                }
            }

            $progressoTotal = 0.0;
            $atrasadas = 0;
            $vencendoBreve = 0;
            $naoHomologadas = 0;
            $pilaresSetor = [];

            foreach ($listaEntregas as $e) {
                $prog = (float) ($e->progresso_realizado ?? 0);
                $progressoTotal += $prog;

                if ((int) ($e->homologado ?? 1) === 0) {
                    $naoHomologadas++;
                }

                $dtFim = $e->data_fim ? Carbon::parse($e->data_fim) : null;
                if ($dtFim && $prog < 100.0) {
                    if ($dtFim->lt($hoje)) {
                        $atrasadas++;
                    } elseif ($dtFim->lte($limiteAlerta)) {
                        $vencendoBreve++;
                    }
                }

                $tags = json_decode($e->etiquetas ?? '[]', true);
                if (is_array($tags)) {
                    foreach ($tags as $tag) {
                        if (($tag['key'] ?? '') === 'pilar' && !empty($tag['value'])) {
                            $pNome = (string) $tag['value'];
                            $pilaresSetor[$pNome] = ($pilaresSetor[$pNome] ?? 0) + 1;
                        }
                    }
                }
            }

            $progressoMedio = $totalEntregas > 0 ? round($progressoTotal / $totalEntregas, 1) : 0.0;
            $repPendentes = $dadosIntegrados['repactuacoes_pendentes_unidade'][$u->id] ?? 0;
            $ptAtivos = $dadosIntegrados['planos_trabalho_unidade'][$u->id] ?? 0;
            $corregUnidade = $dadosIntegrados['corregedoria_unidade'][$u->id] ?? ['enviados' => 0, 'rascunhos' => 0, 'total' => 0];
            $ocorrenciasUnidade = $dadosIntegrados['ocorrencias_unidade'][$u->id] ?? 0;

            [$farol, $situacao, $motivos] = $this->avaliarFarolUnidade(
                ehConsolidadora: $ehConsolidadora,
                planoPrincipal: $planoPrincipal,
                totalEntregas: $totalEntregas,
                progressoMedio: $progressoMedio,
                atrasadas: $atrasadas,
                vencendoBreve: $vencendoBreve,
                naoHomologadas: $naoHomologadas,
                repPendentes: $repPendentes,
                regras: $regras
            );

            if ($farol === 'VERDE') {
                $farolVerdeQtd++;
            } elseif ($farol === 'AMARELO') {
                $farolAmareloQtd++;
            } else {
                $farolVermelhoQtd++;
            }

            arsort($pilaresSetor);
            $pilarPredominante = !empty($pilaresSetor) ? (string) array_key_first($pilaresSetor) : 'Governança / Geral';

            if (!isset($resumoMacro[$macro])) {
                $resumoMacro[$macro] = [
                    'macro_area' => $macro,
                    'total_unidades' => 0,
                    'total_entregas' => 0,
                    'soma_progresso' => 0.0,
                    'verde' => 0,
                    'amarelo' => 0,
                    'vermelho' => 0,
                    'repactuacoes_pendentes' => 0,
                    'planos_individuais' => 0,
                    'progresso_medio' => 0.0,
                ];
            }

            $resumoMacro[$macro]['total_unidades']++;
            $resumoMacro[$macro]['total_entregas'] += $totalEntregas;
            $resumoMacro[$macro]['soma_progresso'] += $progressoTotal;
            $resumoMacro[$macro]['repactuacoes_pendentes'] += $repPendentes;
            $resumoMacro[$macro]['planos_individuais'] += $ptAtivos;
            if ($farol === 'VERDE') {
                $resumoMacro[$macro]['verde']++;
            } elseif ($farol === 'AMARELO') {
                $resumoMacro[$macro]['amarelo']++;
            } else {
                $resumoMacro[$macro]['vermelho']++;
            }

            $unidadesDetalhes[] = [
                'id' => $u->id,
                'sigla' => $u->sigla,
                'nome' => $u->nome,
                'gestor' => $gestoresPorUnidade[$u->id] ?? null,
                'macro_area' => $macro,
                'eh_consolidadora' => $ehConsolidadora,
                'plano_id' => $planoPrincipal->id ?? null,
                'plano_numero' => $planoPrincipal->numero ?? null,
                'plano_nome' => $planoPrincipal->nome ?? null,
                'plano_status' => $planoPrincipal->status ?? null,
                'total_planos' => count($listaPlanos),
                'total_entregas' => $totalEntregas,
                'entregas_atrasadas' => $atrasadas,
                'entregas_vencendo' => $vencendoBreve,
                'progresso_medio' => $progressoMedio,
                'farol' => $farol,
                'situacao' => $situacao,
                'farol_motivos' => $motivos,
                'pilar_predominante' => $pilarPredominante,
                'repactuacoes_pendentes' => $repPendentes,
                'planos_individuais' => $ptAtivos,
                'corregedoria_enviados' => $corregUnidade['enviados'],
                'corregedoria_rascunhos' => $corregUnidade['rascunhos'],
                'ocorrencias_ativas' => $ocorrenciasUnidade,
            ];
        }

        // Calcular progresso médio real por Macro-Área e remover áreas vazias sem unidades
        $macroAreasFinal = [];
        foreach ($resumoMacro as $area) {
            if ($area['total_unidades'] === 0 && $area['macro_area'] === 'Outros Setores') {
                continue;
            }
            $area['progresso_medio'] = $area['total_entregas'] > 0
                ? round($area['soma_progresso'] / $area['total_entregas'], 1)
                : 0.0;
            unset($area['soma_progresso']);
            $macroAreasFinal[] = $area;
        }

        $totalUnidades = count($unidades);
        $cobertura = $unidadesAlvoCobertura > 0
            ? round(($unidadesComPlano / $unidadesAlvoCobertura) * 100, 1)
            : 0.0;
        if ($cobertura > 100.0) {
            $cobertura = 100.0;
        }

        return [
            'config' => $config,
            'atualizado_em' => $hoje->toIso8601String(),
            'competencia_corregedoria' => sprintf('%02d/%04d', $hoje->month, $hoje->year),
            'kpis' => [
                'total_unidades' => $totalUnidades,
                'unidades_alvo_cobertura' => $unidadesAlvoCobertura,
                'unidades_com_plano' => $unidadesComPlano,
                'total_planos_ativos' => count($planos),
                'cobertura_percentual' => $cobertura,
                'total_entregas' => $totalEntregasGlobal,
                'entregas_concluidas' => $totalConcluidasGlobal,
                'entregas_atrasadas' => $totalAtrasadasGlobal,
                'entregas_vencendo_breve' => $totalVencendoGlobal,
                'progresso_medio_global' => $totalEntregasGlobal > 0 ? round($somaProgressoGlobal / $totalEntregasGlobal, 1) : 0.0,
                'projetos_gt_total' => $dadosIntegrados['projetos']['total'],
                'projetos_iniciados' => $dadosIntegrados['projetos']['iniciados'],
                'projetos_concluidos' => $dadosIntegrados['projetos']['concluidos'],
                'projetos_progresso_medio' => $dadosIntegrados['projetos']['progresso_medio'],
                'projetos_marcos_total' => $dadosIntegrados['projetos']['marcos_total'],
                'projetos_marcos_concluidos' => $dadosIntegrados['projetos']['marcos_concluidos'],
                'planos_individuais_total' => $dadosIntegrados['planos_trabalho']['total'],
                'participantes_pgd_ativos' => $dadosIntegrados['planos_trabalho']['participantes'],
                'entregas_desdobradas_pt' => $dadosIntegrados['planos_trabalho']['entregas_desdobradas'],
                'repactuacoes_total' => $dadosIntegrados['repactuacoes']['total'],
                'repactuacoes_pendentes' => $dadosIntegrados['repactuacoes']['pendentes'],
                'repactuacoes_homologadas' => $dadosIntegrados['repactuacoes']['homologadas'],
                'corregedoria_enviados_mes' => $dadosIntegrados['corregedoria']['enviados'],
                'corregedoria_rascunhos_mes' => $dadosIntegrados['corregedoria']['rascunhos'],
                'corregedoria_total_mes' => $dadosIntegrados['corregedoria']['total'],
                'ocorrencias_ativas' => $dadosIntegrados['ocorrencias']['total'],
                'farol' => [
                    'verde' => $farolVerdeQtd,
                    'amarelo' => $farolAmareloQtd,
                    'vermelho' => $farolVermelhoQtd,
                ],
                'faixas_prazo' => $faixasPrazoGlobais,
                'pilares' => $pilaresContagem,
                'pilares_detalhados' => $pilaresDetalhados,
            ],
            'eixos_estrategicos' => $dadosIntegrados['eixos_estrategicos'],
            'macro_areas' => $macroAreasFinal,
            'unidades' => $unidadesDetalhes,
        ];
    }

    /**
     * Carrega detalhamento completo em tempo real de uma unidade específica para modal de drill-down.
     */
    public function getUnidadeDetalhe(string $unidadeId): array
    {
        $config = $this->configService->getConfig();
        $regras = $config['regras_farol'];
        $hoje = now();
        $limiteAlerta = $hoje->copy()->addDays((int) $regras['dias_alerta_vencimento']);

        $unidade = DB::connection('tenant')->table('unidades')
            ->where('id', $unidadeId)
            ->whereNull('deleted_at')
            ->first(['id', 'sigla', 'nome']);

        if (!$unidade) {
            return [];
        }

        $gestor = $this->carregarGestoresPorUnidade()[$unidadeId] ?? null;
        $statusPermitidos = $this->resolverStatusPlanos(
            $config['opcoes']['status_planos_padrao'] ?? 'VALIDOS',
            (bool) $regras['incluir_planos_nao_ativos']
        );

        $planos = DB::connection('tenant')->table('planos_entregas')
            ->where('unidade_id', $unidadeId)
            ->whereNull('deleted_at')
            ->whereIn('status', $statusPermitidos)
            ->orderByDesc('data_inicio')
            ->get(['id', 'numero', 'nome', 'status', 'data_inicio', 'data_fim', 'numero_processo_sei']);

        $planoIds = $planos->pluck('id')->all();

        $entregas = empty($planoIds)
            ? collect()
            : DB::connection('tenant')->table('planos_entregas_entregas as pee')
                ->whereNull('pee.deleted_at')
                ->whereIn('pee.plano_entrega_id', $planoIds)
                ->orderBy('pee.descricao')
                ->get([
                    'pee.id',
                    'pee.plano_entrega_id',
                    'pee.descricao',
                    'pee.descricao_entrega',
                    'pee.descricao_meta',
                    'pee.progresso_esperado',
                    'pee.progresso_realizado',
                    'pee.homologado',
                    'pee.data_inicio',
                    'pee.data_fim',
                    'pee.etiquetas',
                ]);

        $entregaIds = $entregas->pluck('id')->all();
        $objetivosPorEntrega = [];
        if (!empty($entregaIds)) {
            $vincObjs = DB::connection('tenant')->table('planos_entregas_entregas_objetivos as peo')
                ->join('planejamentos_objetivos as po', 'po.id', '=', 'peo.planejamento_objetivo_id')
                ->leftJoin('eixos_tematicos as et', 'et.id', '=', 'po.eixo_tematico_id')
                ->whereNull('peo.deleted_at')
                ->whereNull('po.deleted_at')
                ->whereIn('peo.entrega_id', $entregaIds)
                ->get(['peo.entrega_id', 'po.nome as objetivo_nome', 'et.nome as eixo_nome', 'et.cor as eixo_cor']);

            foreach ($vincObjs as $vo) {
                $objetivosPorEntrega[$vo->entrega_id][] = [
                    'objetivo' => $vo->objetivo_nome,
                    'eixo' => $vo->eixo_nome,
                    'cor' => $vo->eixo_cor,
                ];
            }
        }

        $repactuacoes = Schema::connection('tenant')->hasTable('planos_entregas_repactuacoes')
            ? DB::connection('tenant')->table('planos_entregas_repactuacoes')
                ->where('unidade_id', $unidadeId)
                ->whereNull('deleted_at')
                ->orderByDesc('created_at')
                ->get(['id', 'plano_entrega_entrega_id', 'tipo', 'motivo', 'status', 'descricao_ajuste', 'data_nova', 'created_at'])
            : collect();

        $entregasComRepact = [];
        foreach ($repactuacoes as $r) {
            if ($r->plano_entrega_entrega_id && $r->status === 'PENDENTE') {
                $entregasComRepact[$r->plano_entrega_entrega_id] = true;
            }
        }

        $entregasFormatadas = $entregas->map(function ($e) use ($hoje, $limiteAlerta, $objetivosPorEntrega, $entregasComRepact) {
            $prog = (float) ($e->progresso_realizado ?? 0);
            $dtFim = $e->data_fim ? Carbon::parse($e->data_fim) : null;
            $atrasada = $dtFim && $prog < 100.0 && $dtFim->lt($hoje);
            $vencendo = $dtFim && !$atrasada && $prog < 100.0 && $dtFim->lte($limiteAlerta);

            $tags = json_decode($e->etiquetas ?? '[]', true);
            $pilar = null;
            $prazoLabel = null;
            if (is_array($tags)) {
                foreach ($tags as $t) {
                    if (($t['key'] ?? '') === 'pilar') $pilar = $t['value'] ?? null;
                    if (($t['key'] ?? '') === 'prazo') $prazoLabel = $t['value'] ?? null;
                }
            }

            return [
                'id' => $e->id,
                'plano_entrega_id' => $e->plano_entrega_id,
                'titulo' => $e->descricao,
                'detalhamento' => $e->descricao_entrega,
                'meta_info' => $e->descricao_meta,
                'progresso_realizado' => $prog,
                'homologado' => (bool) $e->homologado,
                'data_inicio' => $e->data_inicio ? substr((string) $e->data_inicio, 0, 10) : null,
                'data_fim' => $e->data_fim ? substr((string) $e->data_fim, 0, 10) : null,
                'pilar' => $pilar,
                'prazo_label' => $prazoLabel,
                'atrasada' => $atrasada,
                'vencendo' => $vencendo,
                'repactuacao_pendente' => isset($entregasComRepact[$e->id]),
                'objetivos' => $objetivosPorEntrega[$e->id] ?? [],
            ];
        })->values()->all();

        $planosIndividuais = DB::connection('tenant')->table('planos_trabalhos as pt')
            ->leftJoin('usuarios as u', 'u.id', '=', 'pt.usuario_id')
            ->where('pt.unidade_id', $unidadeId)
            ->whereNull('pt.deleted_at')
            ->whereIn('pt.status', ['ATIVO', 'CONCLUIDO', 'AVALIADO', 'AGUARDANDO_ASSINATURA'])
            ->orderByDesc('pt.data_inicio')
            ->get(['pt.id', 'pt.numero', 'pt.status', 'pt.data_inicio', 'pt.data_fim', 'u.nome as participante']);

        $relatoriosCorreg = Schema::connection('tenant')->hasTable('relatorios_corregedoria')
            ? DB::connection('tenant')->table('relatorios_corregedoria as rc')
                ->leftJoin('usuarios as u', 'u.id', '=', 'rc.usuario_id')
                ->where('rc.unidade_id', $unidadeId)
                ->where('rc.mes', $hoje->month)
                ->where('rc.ano', $hoje->year)
                ->whereNull('rc.deleted_at')
                ->get(['rc.id', 'rc.status', 'rc.data_assinatura', 'u.nome as usuario_nome'])
            : collect();

        return [
            'unidade' => [
                'id' => $unidade->id,
                'sigla' => $unidade->sigla,
                'nome' => $unidade->nome,
                'gestor' => $gestor,
            ],
            'planos' => $planos,
            'entregas' => $entregasFormatadas,
            'repactuacoes' => $repactuacoes,
            'planos_individuais' => $planosIndividuais,
            'corregedoria_mes' => $relatoriosCorreg,
        ];
    }

    private function carregarUnidades()
    {
        return DB::connection('tenant')->table('unidades')
            ->whereNull('deleted_at')
            ->whereNull('data_inativacao')
            ->orderBy('sigla')
            ->get(['id', 'sigla', 'nome', 'instituidora', 'executora']);
    }

    private function carregarGestoresPorUnidade(): array
    {
        $rows = DB::connection('tenant')->table('unidades_integrantes as ui')
            ->join('unidades_integrantes_atribuicoes as uia', 'uia.unidade_integrante_id', '=', 'ui.id')
            ->join('usuarios as u', 'u.id', '=', 'ui.usuario_id')
            ->whereNull('ui.deleted_at')
            ->whereNull('uia.deleted_at')
            ->whereNull('u.deleted_at')
            ->whereRaw("FIND_IN_SET('GESTOR', uia.atribuicao) > 0")
            ->get(['ui.unidade_id', 'u.nome']);

        $mapa = [];
        foreach ($rows as $row) {
            if (!isset($mapa[$row->unidade_id])) {
                $mapa[$row->unidade_id] = $row->nome;
            }
        }
        return $mapa;
    }

    private function carregarIntegracoesOperacionais($entregas, Carbon $hoje): array
    {
        $entregaIds = $entregas->pluck('id')->all();
        $entregaParaProg = [];
        foreach ($entregas as $e) {
            $entregaParaProg[$e->id] = (float) ($e->progresso_realizado ?? 0);
        }

        // 1. Eixos Estratégicos reais (eixos_tematicos + planejamentos_objetivos)
        $eixos = DB::connection('tenant')->table('eixos_tematicos')
            ->whereNull('deleted_at')
            ->orderBy('nome')
            ->get(['id', 'nome', 'icone', 'cor']);

        $eixosStats = [];
        foreach ($eixos as $ex) {
            $eixosStats[$ex->id] = [
                'id' => $ex->id,
                'nome' => $ex->nome,
                'icone' => $ex->icone ?: 'bi bi-diagram-3',
                'cor' => $ex->cor ?: '#1351b4',
                'entregas_ids' => [],
                'soma_progresso' => 0.0,
                'concluidas' => 0,
            ];
        }

        if (!empty($entregaIds)) {
            $links = DB::connection('tenant')->table('planos_entregas_entregas_objetivos as peo')
                ->join('planejamentos_objetivos as po', 'po.id', '=', 'peo.planejamento_objetivo_id')
                ->whereNull('peo.deleted_at')
                ->whereNull('po.deleted_at')
                ->whereIn('peo.entrega_id', $entregaIds)
                ->whereNotNull('po.eixo_tematico_id')
                ->select(['peo.entrega_id', 'po.eixo_tematico_id'])
                ->distinct()
                ->get();

            foreach ($links as $lk) {
                if (!isset($eixosStats[$lk->eixo_tematico_id])) {
                    continue;
                }
                if (!isset($eixosStats[$lk->eixo_tematico_id]['entregas_ids'][$lk->entrega_id])) {
                    $eixosStats[$lk->eixo_tematico_id]['entregas_ids'][$lk->entrega_id] = true;
                    $prog = $entregaParaProg[$lk->entrega_id] ?? 0.0;
                    $eixosStats[$lk->eixo_tematico_id]['soma_progresso'] += $prog;
                    if ($prog >= 100.0) {
                        $eixosStats[$lk->eixo_tematico_id]['concluidas']++;
                    }
                }
            }
        }

        $totalEntregas = count($entregaIds);
        $eixosFinal = [];
        foreach ($eixosStats as $st) {
            $qtd = count($st['entregas_ids']);
            $eixosFinal[] = [
                'id' => $st['id'],
                'nome' => $st['nome'],
                'icone' => $st['icone'],
                'cor' => $st['cor'],
                'total_entregas' => $qtd,
                'percentual' => $totalEntregas > 0 ? round(($qtd / $totalEntregas) * 100, 1) : 0.0,
                'progresso_medio' => $qtd > 0 ? round($st['soma_progresso'] / $qtd, 1) : 0.0,
                'concluidas' => $st['concluidas'],
            ];
        }

        // 2. Projetos Estruturantes reais (projetos + projetos_tarefas)
        $projetos = DB::connection('tenant')->table('projetos')
            ->whereNull('deleted_at')
            ->where('status', '!=', 'CANCELADO')
            ->get(['id', 'status', 'progresso']);

        $projetosIds = $projetos->pluck('id')->all();
        $marcosTotal = 0;
        $marcosConcluidos = 0;
        if (!empty($projetosIds)) {
            $tarefas = DB::connection('tenant')->table('projetos_tarefas')
                ->whereNull('deleted_at')
                ->whereIn('projeto_id', $projetosIds)
                ->get(['status', 'progresso']);
            $marcosTotal = $tarefas->count();
            foreach ($tarefas as $t) {
                if ((float) $t->progresso >= 100.0 || $t->status === 'CONCLUIDO') {
                    $marcosConcluidos++;
                }
            }
        }

        $projIniciados = $projetos->where('status', 'INICIADO')->count();
        $projConcluidos = $projetos->where('status', 'CONCLUIDO')->count();
        $projTotal = $projetos->count();
        $projSomaProg = (float) $projetos->sum('progresso');

        // 3. Repactuações reais
        $repPendentesUnidade = [];
        $repTotal = 0;
        $repPendentes = 0;
        $repHomologadas = 0;
        if (Schema::connection('tenant')->hasTable('planos_entregas_repactuacoes')) {
            $reps = DB::connection('tenant')->table('planos_entregas_repactuacoes')
                ->whereNull('deleted_at')
                ->get(['unidade_id', 'status']);
            $repTotal = $reps->count();
            foreach ($reps as $r) {
                if ($r->status === 'PENDENTE') {
                    $repPendentes++;
                    $repPendentesUnidade[$r->unidade_id] = ($repPendentesUnidade[$r->unidade_id] ?? 0) + 1;
                } elseif ($r->status === 'HOMOLOGADO') {
                    $repHomologadas++;
                }
            }
        }

        // 4. Planos de Ação Individuais reais (planos_trabalhos + planos_trabalhos_entregas)
        $pts = DB::connection('tenant')->table('planos_trabalhos')
            ->whereNull('deleted_at')
            ->whereIn('status', ['ATIVO', 'CONCLUIDO', 'AVALIADO', 'AGUARDANDO_ASSINATURA'])
            ->get(['unidade_id', 'usuario_id']);

        $ptUnidade = [];
        $participantes = [];
        foreach ($pts as $pt) {
            $ptUnidade[$pt->unidade_id] = ($ptUnidade[$pt->unidade_id] ?? 0) + 1;
            $participantes[$pt->usuario_id] = true;
        }

        $entregasDesdobradas = empty($entregaIds)
            ? 0
            : (int) DB::connection('tenant')->table('planos_trabalhos_entregas')
                ->whereNull('deleted_at')
                ->whereIn('plano_entrega_entrega_id', $entregaIds)
                ->distinct('plano_entrega_entrega_id')
                ->count('plano_entrega_entrega_id');

        // 5. Relatórios Mensais da Corregedoria (competência vigente)
        $corregUnidade = [];
        $corregEnviados = 0;
        $corregRascunhos = 0;
        $corregTotal = 0;
        if (Schema::connection('tenant')->hasTable('relatorios_corregedoria')) {
            $rels = DB::connection('tenant')->table('relatorios_corregedoria')
                ->whereNull('deleted_at')
                ->where('mes', $hoje->month)
                ->where('ano', $hoje->year)
                ->get(['unidade_id', 'status']);
            $corregTotal = $rels->count();
            foreach ($rels as $rl) {
                if (!isset($corregUnidade[$rl->unidade_id])) {
                    $corregUnidade[$rl->unidade_id] = ['enviados' => 0, 'rascunhos' => 0, 'total' => 0];
                }
                $corregUnidade[$rl->unidade_id]['total']++;
                if (in_array($rl->status, ['CONCLUIDO_ASSINADO', 'HOMOLOGADO'], true)) {
                    $corregEnviados++;
                    $corregUnidade[$rl->unidade_id]['enviados']++;
                } else {
                    $corregRascunhos++;
                    $corregUnidade[$rl->unidade_id]['rascunhos']++;
                }
            }
        }

        // 6. Ocorrências / Afastamentos vigentes hoje
        $ocorrenciasUnidade = [];
        $ocorrenciasTotal = 0;
        if (Schema::connection('tenant')->hasTable('afastamentos')) {
            $afastamentos = DB::connection('tenant')->table('afastamentos as a')
                ->leftJoin('unidades_integrantes as ui', function ($join) {
                    $join->on('ui.usuario_id', '=', 'a.usuario_id')->whereNull('ui.deleted_at');
                })
                ->leftJoin('unidades_integrantes_atribuicoes as uia', function ($join) {
                    $join->on('uia.unidade_integrante_id', '=', 'ui.id')->whereNull('uia.deleted_at');
                })
                ->whereNull('a.deleted_at')
                ->where('a.data_inicio', '<=', $hoje)
                ->where('a.data_fim', '>=', $hoje)
                ->where(function ($q) {
                    $q->whereRaw("FIND_IN_SET('LOTADO', uia.atribuicao) > 0")->orWhereNull('ui.unidade_id');
                })
                ->select(['a.id', 'ui.unidade_id'])
                ->distinct()
                ->get();

            $vistosAfast = [];
            foreach ($afastamentos as $af) {
                if (!isset($vistosAfast[$af->id])) {
                    $vistosAfast[$af->id] = true;
                    $ocorrenciasTotal++;
                }
                if ($af->unidade_id) {
                    $ocorrenciasUnidade[$af->unidade_id] = ($ocorrenciasUnidade[$af->unidade_id] ?? 0) + 1;
                }
            }
        }

        return [
            'eixos_estrategicos' => $eixosFinal,
            'projetos' => [
                'total' => $projTotal,
                'iniciados' => $projIniciados,
                'concluidos' => $projConcluidos,
                'progresso_medio' => $projTotal > 0 ? round($projSomaProg / $projTotal, 1) : 0.0,
                'marcos_total' => $marcosTotal,
                'marcos_concluidos' => $marcosConcluidos,
            ],
            'repactuacoes' => [
                'total' => $repTotal,
                'pendentes' => $repPendentes,
                'homologadas' => $repHomologadas,
            ],
            'repactuacoes_pendentes_unidade' => $repPendentesUnidade,
            'planos_trabalho' => [
                'total' => $pts->count(),
                'participantes' => count($participantes),
                'entregas_desdobradas' => $entregasDesdobradas,
            ],
            'planos_trabalho_unidade' => $ptUnidade,
            'corregedoria' => [
                'enviados' => $corregEnviados,
                'rascunhos' => $corregRascunhos,
                'total' => $corregTotal,
            ],
            'corregedoria_unidade' => $corregUnidade,
            'ocorrencias' => [
                'total' => $ocorrenciasTotal,
            ],
            'ocorrencias_unidade' => $ocorrenciasUnidade,
        ];
    }

    private function resolverStatusPlanos(string $filtroStatus, bool $incluirNaoAtivos): array
    {
        return match (strtoupper($filtroStatus)) {
            'ATIVO' => ['ATIVO'],
            'HOMOLOGANDO' => ['HOMOLOGANDO'],
            'CONCLUIDO' => ['CONCLUIDO', 'AVALIADO'],
            default => $incluirNaoAtivos ? ['ATIVO', 'HOMOLOGANDO', 'CONCLUIDO', 'AVALIADO'] : ['ATIVO'],
        };
    }

    private function classificarFaixaPrazo(
        ?Carbon $dtFim,
        ?string $prazoTag,
        Carbon $hoje,
        int $diasCurto,
        int $diasMedio,
        bool $usarTagSgpePrazo
    ): string {
        // Quando a entrega possui data_fim específica (não genérica de fim de ciclo plurianual >= 2030), usa o prazo real do banco
        $temDataEspecifica = $dtFim !== null && $dtFim->year < 2030;

        if ($temDataEspecifica || !$usarTagSgpePrazo || empty($prazoTag)) {
            if ($dtFim === null) {
                return 'estruturante';
            }
            $diasRestantes = $hoje->diffInDays($dtFim, false);
            if ($diasRestantes <= $diasCurto) {
                return 'curto_prazo';
            }
            if ($diasRestantes <= $diasMedio) {
                return 'medio_prazo';
            }
            return 'estruturante';
        }

        // Fallback inteligente para metadado SGPE quando data_fim aponta apenas para o teto do ciclo (2030)
        if (preg_match('/(\d+)\s*dias?/i', $prazoTag, $m)) {
            $dias = (int) $m[1];
            if ($dias <= $diasCurto) {
                return 'curto_prazo';
            }
            if ($dias <= $diasMedio) {
                return 'medio_prazo';
            }
            return 'estruturante';
        }

        if (stripos($prazoTag, 'Quinzenal') !== false || stripos($prazoTag, 'Mensal') !== false) {
            return 'curto_prazo';
        }
        if (stripos($prazoTag, 'Trimestral') !== false || stripos($prazoTag, 'Semestral') !== false) {
            return $diasCurto >= 180 ? 'curto_prazo' : 'medio_prazo';
        }

        return 'estruturante';
    }

    private function montarMapaMacroAreas(array $macroAreasConfig): array
    {
        $mapa = [];
        foreach ($macroAreasConfig as $area) {
            $nome = (string) ($area['nome'] ?? '');
            if ($nome === '') {
                continue;
            }
            foreach ($area['siglas'] ?? [] as $sigla) {
                $mapa[strtoupper(trim((string) $sigla))] = $nome;
            }
        }
        return $mapa;
    }

    /**
     * Avalia o Farol real da unidade combinando existência de plano, atrasos, repactuações, homologação e execução.
     *
     * @return array{0: string, 1: string, 2: string[]}
     */
    private function avaliarFarolUnidade(
        bool $ehConsolidadora,
        ?object $planoPrincipal,
        int $totalEntregas,
        float $progressoMedio,
        int $atrasadas,
        int $vencendoBreve,
        int $naoHomologadas,
        int $repPendentes,
        array $regras
    ): array {
        if ($ehConsolidadora && !$planoPrincipal) {
            return ['VERDE', 'Unidade Consolidadora', ['Unidade configurada como consolidadora institucional']];
        }

        if (!$planoPrincipal) {
            return ['VERMELHO', 'Sem Plano Setorial Válido', ['Nenhum Plano de Ação Setorial ativo ou vigente encontrado']];
        }

        $motivos = [];
        $limiteVermelhoAtrasos = max(1, (int) ($regras['limite_vermelho_atrasos'] ?? 1));
        $progVerdeMin = (float) ($regras['progresso_verde_min'] ?? 0);
        $progAmareloMin = (float) ($regras['progresso_amarelo_min'] ?? 0);

        if ($atrasadas >= $limiteVermelhoAtrasos) {
            $motivos[] = "{$atrasadas} entrega(s) com prazo vencido e conclusão < 100%";
        }

        if ($totalEntregas > 0 && $progAmareloMin > 0 && $progressoMedio < $progAmareloMin) {
            $motivos[] = "Execução média ({$progressoMedio}%) abaixo do mínimo crítico ({$progAmareloMin}%)";
        }

        if (!empty($motivos)) {
            return ['VERMELHO', 'Alerta de Prazo / Execução', $motivos];
        }

        if ($totalEntregas === 0) {
            return ['AMARELO', 'Plano sem Entregas', ["Plano ({$planoPrincipal->status}) cadastrado sem entregas vinculadas"]];
        }

        if ($planoPrincipal->status === 'HOMOLOGANDO') {
            $motivos[] = 'Plano aguardando homologação';
        }
        if ($atrasadas > 0) {
            $motivos[] = "{$atrasadas} entrega(s) em atraso";
        }
        if ($vencendoBreve > 0) {
            $motivos[] = "{$vencendoBreve} entrega(s) vencendo nos próximos {$regras['dias_alerta_vencimento']} dias";
        }
        if ($naoHomologadas > 0) {
            $motivos[] = "{$naoHomologadas} entrega(s) pendente(s) de homologação";
        }
        if (!empty($regras['repactuacao_pendente_alerta']) && $repPendentes > 0) {
            $motivos[] = "{$repPendentes} repactuação(ões) aguardando deliberação";
        }
        if ($progVerdeMin > 0 && $progressoMedio < $progVerdeMin) {
            $motivos[] = "Execução média ({$progressoMedio}%) abaixo da meta esperada ({$progVerdeMin}%)";
        }

        if (!empty($motivos)) {
            return ['AMARELO', 'Atenção / Pendência', $motivos];
        }

        return [
            'VERDE',
            $progressoMedio >= 100 ? 'Concluído / Meta Atingida' : 'Regular / No Prazo',
            ["Plano {$planoPrincipal->status} com {$totalEntregas} entrega(s) e execução média de {$progressoMedio}%"],
        ];
    }
}
