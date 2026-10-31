<?php

declare(strict_types=1);

namespace App\V2\Home\DataProviders;

use App\Repository\UnidadeRepository;
use App\V2\Home\DTOs\HomeRequestDTO;
use App\V2\Home\Traits\ResolveUnidades;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AgendaMarcos
{
    use ResolveUnidades;

    public function __construct(
        private readonly UnidadeRepository $unidadeRepository,
    ) {}

    protected function getUnidadeRepository(): UnidadeRepository
    {
        return $this->unidadeRepository;
    }

    /**
     * @return array{
     *   total_atencao: int,
     *   total_proximos: int,
     *   itens: array<int, array{
     *     id: string,
     *     tipo: string,
     *     categoria: string,
     *     titulo: string,
     *     unidade: string,
     *     prazo: string,
     *     status: string,
     *     pilar: string,
     *     rota: string[],
     *   }>
     * }
     */
    public function getData(HomeRequestDTO $dto): array
    {
        $unidadeIds = $this->resolverUnidades($dto);
        $siglas = DB::table('unidades')->whereIn('id', $unidadeIds)->pluck('sigla')->toArray();
        $isSede = in_array('PGE-MA', $siglas) || count($unidadeIds) >= 40;

        $itens = [];

        // 1. Marco do Relatório Mensal de Atividades (Corregedoria)
        if (Schema::hasTable('relatorios_corregedoria')) {
            $hoje = now();
            $mesAtual = $hoje->month;
            $anoAtual = $hoje->year;

            $relatorio = DB::table('relatorios_corregedoria')
                ->where('usuario_id', $dto->usuarioId)
                ->where('mes', $mesAtual)
                ->where('ano', $anoAtual)
                ->whereNull('deleted_at')
                ->first();

            $status = $relatorio ? $relatorio->status : 'NAO_INICIADO';

            if ($status !== 'HOMOLOGADO') {
                $statusDesc = match ($status) {
                    'RASCUNHO' => 'Rascunho em andamento',
                    'CONCLUIDO_ASSINADO' => 'Aguardando Homologação',
                    'RETIFICACAO' => 'Em Retificação',
                    default => 'Pendente de Preenchimento',
                };

                $categoria = in_array($status, ['RASCUNHO', 'NAO_INICIADO', 'RETIFICACAO']) ? 'VENCENDO' : 'PROXIMO';

                $meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
                $nomeMes = $meses[$mesAtual] ?? (string)$mesAtual;

                $itens[] = [
                    'id' => 'corregedoria-' . $mesAtual . '-' . $anoAtual,
                    'tipo' => 'CORREGEDORIA',
                    'categoria' => $categoria,
                    'titulo' => "Fechamento do Relatório Mensal de Atividades ({$nomeMes}/{$anoAtual})",
                    'unidade' => 'Corregedoria-Geral',
                    'prazo' => 'Até o 5º dia útil',
                    'status' => $statusDesc,
                    'pilar' => '1º P: Prazo',
                    'rota' => ['gestao', 'corregedoria', 'meu-relatorio'],
                ];
            }
        }

        // 2. Marcos dos Projetos Estruturantes (GTs)
        if (Schema::hasTable('sgpe_projetos')) {
            $projetosQuery = DB::table('sgpe_projetos');

            if (!$isSede) {
                $projetosQuery->where(function ($q) use ($siglas) {
                    foreach ($siglas as $sigla) {
                        $q->orWhere('lider', 'like', "%{$sigla}%")
                          ->orWhere('unidades_participantes', 'like', "%{$sigla}%");
                    }
                });
            }

            $projetos = $projetosQuery->get();

            foreach ($projetos as $proj) {
                // Classificação inteligente de urgência
                $prazoLower = mb_strtolower((string)$proj->prazo);
                $categoria = 'PROXIMO';

                if (str_contains($prazoLower, '30 dias') || str_contains($prazoLower, 'imediato') || str_contains($prazoLower, 'pronto')) {
                    $categoria = 'VENCENDO';
                }

                $itens[] = [
                    'id' => 'projeto-' . $proj->id,
                    'tipo' => 'PROJETO',
                    'categoria' => $categoria,
                    'titulo' => "[{$proj->codigo}] {$proj->titulo}",
                    'unidade' => (string)$proj->lider,
                    'prazo' => (string)$proj->prazo,
                    'status' => (string)$proj->status,
                    'pilar' => '1º P: Prazo',
                    'rota' => ['gestao', 'projeto'],
                ];
            }
        }

        // 3. Iniciativas Prioritárias de Curto Prazo das Unidades no Escopo
        if (Schema::hasTable('sgpe_iniciativas')) {
            $prazosCurtos = ['30 dias', '45 dias', '60 dias', '90 dias'];

            $iniciativasQuery = DB::table('sgpe_iniciativas')
                ->whereIn('prazo', $prazosCurtos);

            if (!$isSede) {
                $iniciativasQuery->whereIn('sigla_unidade', $siglas);
            }

            $iniciativas = $iniciativasQuery
                ->limit(10)
                ->get();

            foreach ($iniciativas as $inic) {
                $categoria = in_array($inic->prazo, ['30 dias', '45 dias', '60 dias']) ? 'VENCENDO' : 'PROXIMO';

                $itens[] = [
                    'id' => 'iniciativa-' . $inic->id,
                    'tipo' => 'INICIATIVA',
                    'categoria' => $categoria,
                    'titulo' => (string)$inic->acao,
                    'unidade' => (string)$inic->sigla_unidade,
                    'prazo' => (string)$inic->prazo,
                    'status' => (string)$inic->status_execucao,
                    'pilar' => (string)$inic->pilar_3ps,
                    'rota' => ['gestao', 'planejamento'],
                ];
            }
        }

        $totalAtencao = count(array_filter($itens, fn ($i) => in_array($i['categoria'], ['VENCIDO', 'VENCENDO'])));
        $totalProximos = count(array_filter($itens, fn ($i) => $i['categoria'] === 'PROXIMO'));

        return [
            'total_atencao' => $totalAtencao,
            'total_proximos' => $totalProximos,
            'itens' => $itens,
        ];
    }
}
