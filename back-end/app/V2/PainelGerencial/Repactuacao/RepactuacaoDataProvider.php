<?php

declare(strict_types=1);

namespace App\V2\PainelGerencial\Repactuacao;

use App\Models\PlanoEntregaEntrega;
use App\Models\PlanoEntregaRepactuacao;
use App\Repository\UnidadeRepository;
use App\V2\PainelGerencial\Traits\ResolveHierarquiaPainel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RepactuacaoDataProvider
{
    use ResolveHierarquiaPainel;

    public function __construct(
        private readonly UnidadeRepository $unidadeRepository,
    ) {}

    protected function getUnidadeRepository(): UnidadeRepository
    {
        return $this->unidadeRepository;
    }

    public function getDadosMonitoramento(?string $ciclo = null, ?string $status = null, ?string $unidadeId = null): array
    {
        $query = DB::connection('tenant')->table('planos_entregas_repactuacoes as r')
            ->leftJoin('unidades as u', 'u.id', '=', 'r.unidade_id')
            ->leftJoin('planos_entregas_entregas as pee', 'pee.id', '=', 'r.plano_entrega_entrega_id')
            ->leftJoin('usuarios as usol', 'usol.id', '=', 'r.solicitante_id')
            ->leftJoin('usuarios as uana', 'uana.id', '=', 'r.analisador_id')
            ->whereNull('r.deleted_at')
            ->select([
                'r.id',
                'r.plano_entrega_id',
                'r.plano_entrega_entrega_id',
                'r.unidade_id',
                'u.sigla as unidade_sigla',
                'u.nome as unidade_nome',
                'r.ciclo',
                'r.tipo',
                'r.motivo',
                'r.descricao_ajuste',
                'r.justificativa',
                'r.data_anterior',
                'r.data_nova',
                'r.meta_anterior',
                'r.meta_nova',
                'r.status',
                'r.solicitante_id',
                'usol.nome as solicitante_nome',
                'r.analisador_id',
                'uana.nome as analisador_nome',
                'r.parecer_analise',
                'r.data_homologacao',
                'r.created_at',
                'r.updated_at',
                'pee.descricao_entrega',
                'pee.meta as meta_atual_entrega',
                'pee.progresso_realizado as progresso_atual_entrega'
            ]);

        if (!empty($ciclo) && $ciclo !== 'TODOS') {
            $query->where('r.ciclo', $ciclo);
        }

        if (!empty($status) && $status !== 'TODOS') {
            $query->where('r.status', $status);
        }

        if (!empty($unidadeId) && $unidadeId !== 'TODAS') {
            $query->where('r.unidade_id', $unidadeId);
        }

        $repactuacoes = $query->orderByRaw("CASE WHEN r.status = 'PENDENTE' THEN 1 ELSE 2 END")
            ->orderBy('r.created_at', 'desc')
            ->get();

        $allRecords = DB::connection('tenant')->table('planos_entregas_repactuacoes')
            ->whereNull('deleted_at')
            ->get();

        $totalSolicitacoes = $allRecords->count();
        $pendentes = $allRecords->where('status', 'PENDENTE')->count();
        $homologadas = $allRecords->where('status', 'HOMOLOGADO')->count();
        $rejeitadas = $allRecords->where('status', 'REJEITADO')->count();
        $unidadesImpactadas = $allRecords->pluck('unidade_id')->unique()->count();

        // Breakdown por Motivo
        $motivosCount = [];
        $motivoLabels = [
            'IMPEDIMENTO_ORCAMENTARIO' => 'Impedimento Orçamentário / BID',
            'ATRASO_EXTERNO' => 'Atraso Externo / Órgãos e Tribunais',
            'REORGANIZACAO_INTERNA' => 'Reorganização Interna de Equipe',
            'EXPANSAO_DEMANDA' => 'Expansão de Demanda Institucional',
            'ADEQUACAO_TECNICA' => 'Adequação Técnica / Ferramental',
            'OUTRO' => 'Outros Motivos Justificados',
        ];

        foreach ($motivoLabels as $key => $label) {
            $count = $allRecords->where('motivo', $key)->count();
            $motivosCount[] = [
                'motivo' => $key,
                'label' => $label,
                'total' => $count,
                'percentual' => $totalSolicitacoes > 0 ? round(($count / $totalSolicitacoes) * 100, 1) : 0
            ];
        }

        // Breakdown por Ciclo
        $ciclos = ['2026-T1', '2026-T2', '2026-T3', '2026-T4', '2026-S1', '2026-S2'];
        $ciclosSummary = [];
        foreach ($ciclos as $c) {
            $recCiclo = $allRecords->where('ciclo', $c);
            $ciclosSummary[] = [
                'ciclo' => $c,
                'total' => $recCiclo->count(),
                'pendentes' => $recCiclo->where('status', 'PENDENTE')->count(),
                'homologadas' => $recCiclo->where('status', 'HOMOLOGADO')->count(),
            ];
        }

        // Unidades disponíveis para filtro
        $unidadesDisponiveis = DB::connection('tenant')->table('unidades')
            ->whereNull('deleted_at')
            ->select('id', 'sigla', 'nome')
            ->orderBy('sigla')
            ->get();

        return [
            'totais' => [
                'total_solicitacoes' => $totalSolicitacoes,
                'pendentes' => $pendentes,
                'homologadas' => $homologadas,
                'rejeitadas' => $rejeitadas,
                'unidades_impactadas' => $unidadesImpactadas,
                'taxa_homologacao' => ($homologadas + $rejeitadas) > 0 
                    ? round(($homologadas / ($homologadas + $rejeitadas)) * 100, 1) 
                    : 100.0,
            ],
            'por_motivo' => $motivosCount,
            'por_ciclo' => $ciclosSummary,
            'unidades' => $unidadesDisponiveis,
            'repactuacoes' => $repactuacoes->map(function ($r) {
                return [
                    'id' => $r->id,
                    'plano_entrega_id' => $r->plano_entrega_id,
                    'plano_entrega_entrega_id' => $r->plano_entrega_entrega_id,
                    'unidade_id' => $r->unidade_id,
                    'unidade_sigla' => $r->unidade_sigla,
                    'unidade_nome' => $r->unidade_nome,
                    'ciclo' => $r->ciclo,
                    'tipo' => $r->tipo,
                    'motivo' => $r->motivo,
                    'descricao_ajuste' => $r->descricao_ajuste,
                    'justificativa' => $r->justificativa,
                    'data_anterior' => $r->data_anterior,
                    'data_nova' => $r->data_nova,
                    'meta_anterior' => $r->meta_anterior,
                    'meta_nova' => $r->meta_nova,
                    'status' => $r->status,
                    'solicitante_id' => $r->solicitante_id,
                    'solicitante_nome' => $r->solicitante_nome ?? 'Gestor Setorial',
                    'analisador_id' => $r->analisador_id,
                    'analisador_nome' => $r->analisador_nome,
                    'parecer_analise' => $r->parecer_analise,
                    'data_homologacao' => $r->data_homologacao,
                    'created_at' => $r->created_at,
                    'descricao_entrega' => $r->descricao_entrega,
                    'meta_atual_entrega' => $r->meta_atual_entrega,
                    'progresso_atual_entrega' => $r->progresso_atual_entrega,
                ];
            })
        ];
    }

    public function homologar(string $id, ?string $parecer, string $analisadorId): array
    {
        $repactuacao = DB::connection('tenant')->table('planos_entregas_repactuacoes')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$repactuacao) {
            throw new \InvalidArgumentException('Solicitação de repactuação não encontrada.');
        }

        DB::connection('tenant')->beginTransaction();
        try {
            DB::connection('tenant')->table('planos_entregas_repactuacoes')
                ->where('id', $id)
                ->update([
                    'status' => 'HOMOLOGADO',
                    'analisador_id' => $analisadorId,
                    'parecer_analise' => $parecer ?? 'Homologado pela Gestão Estratégica / Gabinete.',
                    'data_homologacao' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

            // Se for alteração de prazo ou meta em uma entrega específica, refletir no registro da entrega
            if ($repactuacao->plano_entrega_entrega_id) {
                $updates = ['updated_at' => Carbon::now()];
                if ($repactuacao->data_nova) {
                    $updates['data_fim'] = $repactuacao->data_nova;
                }
                if ($repactuacao->meta_nova) {
                    $updates['meta'] = $repactuacao->meta_nova;
                    $updates['descricao_meta'] = $repactuacao->meta_nova;
                }
                DB::connection('tenant')->table('planos_entregas_entregas')
                    ->where('id', $repactuacao->plano_entrega_entrega_id)
                    ->update($updates);
            }

            DB::connection('tenant')->commit();
            return ['success' => true, 'mensagem' => 'Repactuação homologada com sucesso.'];
        } catch (\Throwable $e) {
            DB::connection('tenant')->rollBack();
            throw $e;
        }
    }

    public function rejeitar(string $id, string $parecer, string $analisadorId): array
    {
        $repactuacao = DB::connection('tenant')->table('planos_entregas_repactuacoes')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$repactuacao) {
            throw new \InvalidArgumentException('Solicitação de repactuação não encontrada.');
        }

        DB::connection('tenant')->table('planos_entregas_repactuacoes')
            ->where('id', $id)
            ->update([
                'status' => 'REJEITADO',
                'analisador_id' => $analisadorId,
                'parecer_analise' => $parecer,
                'data_homologacao' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

        return ['success' => true, 'mensagem' => 'Solicitação de repactuação devolvida/rejeitada com justificativa.'];
    }

    public function criar(array $dados, string $solicitanteId): array
    {
        $id = (string) Str::uuid();

        // Buscar dados anteriores da entrega se informada
        $dataAnterior = null;
        $metaAnterior = null;
        if (!empty($dados['plano_entrega_entrega_id'])) {
            $entrega = DB::connection('tenant')->table('planos_entregas_entregas')
                ->where('id', $dados['plano_entrega_entrega_id'])
                ->first();
            if ($entrega) {
                $dataAnterior = $entrega->data_fim;
                $metaAnterior = $entrega->meta;
                if (empty($dados['plano_entrega_id'])) {
                    $dados['plano_entrega_id'] = $entrega->plano_entrega_id;
                }
                if (empty($dados['unidade_id'])) {
                    $dados['unidade_id'] = $entrega->unidade_id;
                }
            }
        }

        DB::connection('tenant')->table('planos_entregas_repactuacoes')->insert([
            'id' => $id,
            'plano_entrega_id' => $dados['plano_entrega_id'],
            'plano_entrega_entrega_id' => $dados['plano_entrega_entrega_id'] ?? null,
            'unidade_id' => $dados['unidade_id'],
            'ciclo' => $dados['ciclo'] ?? '2026-T1',
            'tipo' => $dados['tipo'] ?? 'PRAZO',
            'motivo' => $dados['motivo'] ?? 'OUTRO',
            'descricao_ajuste' => $dados['descricao_ajuste'],
            'justificativa' => $dados['justificativa'],
            'data_anterior' => $dataAnterior,
            'data_nova' => $dados['data_nova'] ?? null,
            'meta_anterior' => $metaAnterior,
            'meta_nova' => $dados['meta_nova'] ?? null,
            'status' => 'PENDENTE',
            'solicitante_id' => $solicitanteId,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return ['success' => true, 'id' => $id, 'mensagem' => 'Solicitação de repactuação enviada ao Gabinete com sucesso.'];
    }
}
