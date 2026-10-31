<?php

declare(strict_types=1);

namespace App\V2\Home\DataProviders;

use App\Enums\PerfilEnum;
use App\Enums\StatusEnum;
use App\Models\Usuario;
use App\Repository\UnidadeRepository;
use App\Services\CalendarioService;
use App\V2\Home\DTOs\HomeRequestDTO;
use App\V2\Home\Traits\ResolveUnidades;
use App\Traits\SqlPlaceholders;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResumoEquipe
{
    use ResolveUnidades;
    use SqlPlaceholders;

    private const PARTICIPA_PGD = 'sim';

    public function __construct(
        private readonly UnidadeRepository $unidadeRepository,
        private readonly CalendarioService $calendarioService,
    ) {}

    protected function getUnidadeRepository(): UnidadeRepository
    {
        return $this->unidadeRepository;
    }

    public function getData(HomeRequestDTO $dto): array
    {
        $unidadeIds = $this->resolverUnidades($dto);

        $sgpeMetrics = $this->calcularMetricasSGPE($unidadeIds);

        return [
            'unidades' => $this->unidades($unidadeIds),
            'participantes_pgd' => $this->participantesEquipe($unidadeIds),
            'capacidade_equipe_horas_mensais' => $sgpeMetrics['total'] > 0 ? $sgpeMetrics['total'] : $this->capacidadeEquipe($unidadeIds),
            'acoes_estrategicas_total' => $sgpeMetrics['total'],
            'acoes_p1_prazo' => $sgpeMetrics['p1_prazo'],
            'acoes_p2_presenca' => $sgpeMetrics['p2_presenca'],
            'acoes_p3_padronizacao' => $sgpeMetrics['p3_padronizacao'],
            'acoes_outros_eixos' => $sgpeMetrics['outros_eixos'],
        ];
    }

    private function unidades(array $unidadeIds): array
    {
        $unidades = DB::table('unidades')
            ->whereIn('id', $unidadeIds)
            ->whereNull('deleted_at')
            ->get(['executora']);

        $total = $unidades->count();
        $executoras = $unidades->filter(fn ($u) => (bool) $u->executora)->count();

        return [
            'quantidade' => $executoras,
            'total' => $total,
            'percentual' => $total > 0 ? round(($executoras / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Indicadores de Integrantes e Agentes Públicos.
     * Na PGE-MA, todos os agentes públicos ativos nas unidades contam como integrantes.
     */
    private function participantesEquipe(array $unidadeIds): array
    {
        $universo = Usuario::query()
            ->with('perfil')
            ->whereHas('unidadesIntegrantes', fn ($q) => $q
                ->whereIn('unidade_id', $unidadeIds)
                ->whereHas('atribuicoes')
            )
            ->get();

        $agentesPublicos = $universo->filter(fn ($u) => !$this->isPerfilConsulta($u));
        $total = $agentesPublicos->count();

        // Se houver indicação PGD, respeita; caso contrário, todos os agentes ativos na unidade são integrantes
        $participantesComFlag = $agentesPublicos->filter(fn ($u) => $u->participa_pgd === self::PARTICIPA_PGD)->count();
        $quantidade = $participantesComFlag > 0 ? $participantesComFlag : $total;

        return [
            'quantidade' => $quantidade,
            'total' => $total,
            'percentual' => $total > 0 ? round(($quantidade / $total) * 100, 1) : 100,
        ];
    }

    private function isPerfilConsulta(Usuario $usuario): bool
    {
        return $usuario->perfil?->nivel === PerfilEnum::CONSULTA->value;
    }

    /**
     * Extrai as métricas de iniciativas estratégicas do SGPE mapeadas para o escopo de unidades.
     *
     * @return array{total: int, p1_prazo: int, p2_presenca: int, p3_padronizacao: int, outros_eixos: int}
     */
    private function calcularMetricasSGPE(array $unidadeIds): array
    {
        if (!Schema::hasTable('sgpe_iniciativas')) {
            return [
                'total' => 0,
                'p1_prazo' => 0,
                'p2_presenca' => 0,
                'p3_padronizacao' => 0,
                'outros_eixos' => 0,
            ];
        }

        $siglas = DB::table('unidades')
            ->whereIn('id', $unidadeIds)
            ->pluck('sigla')
            ->toArray();

        $query = DB::table('sgpe_iniciativas');

        // Se não incluir a Sede (que representa o órgão todo), filtra pelas siglas específicas das unidades no escopo
        if (!in_array('PGE-MA', $siglas)) {
            $query->whereIn('sigla_unidade', $siglas);
        }

        $total = (clone $query)->count();
        $p1 = (clone $query)->where('pilar_3ps', 'like', '%1º P%')->count();
        $p2 = (clone $query)->where('pilar_3ps', 'like', '%2º P%')->count();
        $p3 = (clone $query)->where('pilar_3ps', 'like', '%3º P%')->count();
        $outros = $total - ($p1 + $p2 + $p3);

        return [
            'total' => $total,
            'p1_prazo' => $p1,
            'p2_presenca' => $p2,
            'p3_padronizacao' => $p3,
            'outros_eixos' => max(0, $outros),
        ];
    }

    private function capacidadeEquipe(array $unidadeIds): float
    {
        $inicioMes = now()->startOfMonth();
        $fimMes = now()->endOfMonth();

        $planos = DB::select(<<<SQL
            SELECT pt.id, pt.carga_horaria, DATE(pt.data_inicio) AS data_inicio, DATE(pt.data_fim) AS data_fim, pt.unidade_id
            FROM planos_trabalhos pt
            WHERE pt.deleted_at IS NULL
              AND pt.status = ?
              AND DATE(pt.data_inicio) <= ?
              AND DATE(pt.data_fim) >= ?
              AND pt.unidade_id IN ({$this->sqlPlaceholders($unidadeIds)})
            ORDER BY pt.unidade_id
        SQL, [StatusEnum::ATIVO->value, $fimMes->toDateString(), $inicioMes->toDateString(), ...$unidadeIds]);

        if (empty($planos)) {
            return 0;
        }

        $horasTotal = 0.0;
        $unidadeAtual = null;
        $feriadosUnidade = [];

        foreach ($planos as $pt) {
            if ($pt->unidade_id !== $unidadeAtual) {
                $unidadeAtual = $pt->unidade_id;
                $feriadosUnidade = CalendarioService::feriadosCadastrados($unidadeAtual);
            }

            $ptInicio = max($inicioMes->toDateString(), $pt->data_inicio);
            $ptFim = min($fimMes->toDateString(), $pt->data_fim);

            $diasUteis = $this->calendarioService->contarDiasUteis($ptInicio, $ptFim, $feriadosUnidade);

            $horasTotal += (float) $pt->carga_horaria * $diasUteis;
        }

        return round($horasTotal, 1);
    }
}
