<?php

declare(strict_types=1);

namespace App\V2\Home\DataProviders;

use App\Enums\PerfilEnum;
use App\Enums\StatusEnum;
use App\Models\Unidade;
use App\Models\Usuario;
use App\Repository\UnidadeRepository;
use App\V2\Home\DTOs\HomeRequestDTO;
use App\V2\Home\Traits\ResolveUnidades;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlanosVigentes
{
    use ResolveUnidades;

    private const PARTICIPA_PGD = 'sim';

    public function __construct(
        private readonly UnidadeRepository $unidadeRepository,
    ) {}

    protected function getUnidadeRepository(): UnidadeRepository
    {
        return $this->unidadeRepository;
    }

    /**
     * @return array{
     *   unidades_com_plano_entregas: array{quantidade: int, total: int, percentual: float},
     *   participantes_com_plano_trabalho: array{quantidade: int, total: int, percentual: float},
     * }
     */
    public function getData(HomeRequestDTO $dto): array
    {
        $escopo = $this->resolverUnidades($dto);

        return [
            'unidades_com_plano_entregas' => $this->indicadorUnidadesComPE($escopo),
            'participantes_com_plano_trabalho' => $this->indicadorParticipantesComPT($escopo),
        ];
    }

    /**
     * @return array{quantidade: int, total: int, percentual: float}
     */
    private function indicadorUnidadesComPE(array $unidadesEscopo): array
    {
        $unidades = $this->unidadesExecutorasQuery($unidadesEscopo)->get(['id', 'sigla']);
        $total = $unidades->count();

        if ($total === 0) {
            return ['quantidade' => 0, 'total' => 0, 'percentual' => 0.0];
        }

        $hoje = now()->toDateString();

        // 1. Unidades com Plano de Entrega ativo (legado / padrão)
        $comPE = Unidade::query()
            ->whereIn('id', $unidades->pluck('id'))
            ->whereHas('planosEntrega', function ($q) use ($hoje) {
                $q->where('status', StatusEnum::ATIVO->value)
                    ->where('data_inicio', '<=', $hoje)
                    ->where('data_fim', '>=', $hoje);
            })
            ->pluck('id')
            ->toArray();

        // 2. Unidades com Planos de Ação Setoriais mapeados no SGPE (PGE-MA)
        $comSGPE = [];
        if (Schema::hasTable('sgpe_iniciativas')) {
            $siglasComSGPE = DB::table('sgpe_iniciativas')
                ->distinct()
                ->pluck('sigla_unidade')
                ->toArray();

            $comSGPE = $unidades
                ->filter(fn ($u) => in_array($u->sigla, $siglasComSGPE))
                ->pluck('id')
                ->toArray();
        }

        $unidadesAtivas = array_unique(array_merge($comPE, $comSGPE));
        $quantidade = count($unidadesAtivas);

        return [
            'quantidade' => $quantidade,
            'total' => $total,
            'percentual' => round(($quantidade / $total) * 100, 1),
        ];
    }

    /**
     * Unidades executoras dentro do escopo. Retorna um builder novo a cada chamada
     * para que os filtros de "quantidade" não vazem para a contagem de "total".
     *
     * @param string[] $unidadesEscopo
     * @return \Illuminate\Database\Eloquent\Builder<Unidade>
     */
    private function unidadesExecutorasQuery(array $unidadesEscopo): \Illuminate\Database\Eloquent\Builder
    {
        return Unidade::query()
            ->where('executora', true)
            ->whereIn('id', $unidadesEscopo);
    }

    /**
     * @return array{quantidade: int, total: int, percentual: float}
     */
    private function indicadorParticipantesComPT(array $unidadesEscopo): array
    {
        $agentes = $this->agentesQuery($unidadesEscopo)->get(['id']);
        $total = $agentes->count();

        if ($total === 0) {
            return ['quantidade' => 0, 'total' => 0, 'percentual' => 0.0];
        }

        $hoje = now();
        $hojeStr = $hoje->toDateString();
        $mes = $hoje->month;
        $ano = $hoje->year;

        // 1. Participantes com Plano de Trabalho ativo (legado / padrão)
        $comPT = Usuario::query()
            ->whereIn('id', $agentes->pluck('id'))
            ->whereHas('planosTrabalho', function ($q) use ($hojeStr, $unidadesEscopo) {
                $q->where('status', StatusEnum::ATIVO->value)
                    ->where('data_inicio', '<=', $hojeStr)
                    ->where('data_fim', '>=', $hojeStr)
                    ->whereIn('unidade_id', $unidadesEscopo);
            })
            ->pluck('id')
            ->toArray();

        // 2. Participantes com Relatório Mensal de Atividades na competência atual (PGE-MA)
        $comRelatorio = [];
        if (Schema::hasTable('relatorios_corregedoria')) {
            $comRelatorio = DB::table('relatorios_corregedoria')
                ->whereIn('usuario_id', $agentes->pluck('id'))
                ->where('ano', $ano)
                ->where('mes', $mes)
                ->whereNull('deleted_at')
                ->pluck('usuario_id')
                ->toArray();
        }

        $ativos = array_unique(array_merge($comPT, $comRelatorio));
        $quantidade = count($ativos);

        return [
            'quantidade' => $quantidade,
            'total' => $total,
            'percentual' => round(($quantidade / $total) * 100, 1),
        ];
    }

    /**
     * Integrantes/Agentes dentro do escopo.
     * Considera agentes ativos vinculados à unidade, com perfil operacional.
     *
     * @param string[] $unidadesEscopo
     * @return \Illuminate\Database\Eloquent\Builder<Usuario>
     */
    private function agentesQuery(array $unidadesEscopo): \Illuminate\Database\Eloquent\Builder
    {
        return Usuario::query()
            ->where(function ($q) {
                $q->where('participa_pgd', self::PARTICIPA_PGD)
                    ->orWhereNull('participa_pgd')
                    ->orWhere('participa_pgd', '');
            })
            ->whereHas('perfil', fn ($p) => $p->where('nivel', '!=', PerfilEnum::CONSULTA->value))
            ->whereHas('unidadesIntegrantes', function ($q) use ($unidadesEscopo) {
                $q->whereIn('unidade_id', $unidadesEscopo)
                    ->whereHas('atribuicoes');
            });
    }
}
