<?php

declare(strict_types=1);

namespace App\V2\Home\DataProviders;

use App\Repository\UnidadeRepository;
use App\V2\Home\DTOs\HomeRequestDTO;
use App\V2\Home\Traits\ResolveUnidades;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

    private function calcularQuintoDiaUtil(int $ano, int $mes): Carbon
    {
        $data = Carbon::create($ano, $mes, 1, 0, 0, 0);
        $uteis = 0;
        while ($uteis < 5) {
            if (!$data->isWeekend()) {
                $uteis++;
            }
            if ($uteis < 5) {
                $data->addDay();
            }
        }
        return $data;
    }

    private function ajustarParaDiaUtil(Carbon $data): Carbon
    {
        $copia = $data->copy();
        while ($copia->isWeekend()) {
            $copia->subDay();
        }
        return $copia;
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
     *     data_evento: ?string,
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
        $hoje = now();
        $mesAtual = (int) $hoje->month;
        $anoAtual = (int) $hoje->year;

        // 1. Marco do Relatório Mensal de Atividades (Corregedoria)
        if (Schema::hasTable('relatorios_corregedoria')) {
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
                $nomeMes = $meses[$mesAtual] ?? (string) $mesAtual;
                $dtQuintoUtil = $this->calcularQuintoDiaUtil($anoAtual, $mesAtual);

                $itens[] = [
                    'id' => 'corregedoria-' . $mesAtual . '-' . $anoAtual,
                    'tipo' => 'CORREGEDORIA',
                    'categoria' => $categoria,
                    'titulo' => "Fechamento do Relatório Mensal de Atividades ({$nomeMes}/{$anoAtual})",
                    'unidade' => 'Corregedoria-Geral',
                    'prazo' => $dtQuintoUtil->format('d/m/Y') . ' (5º dia útil)',
                    'data_evento' => $dtQuintoUtil->format('Y-m-d'),
                    'status' => $statusDesc,
                    'pilar' => '1º P: Prazo',
                    'rota' => ['gestao', 'corregedoria', 'meu-relatorio'],
                ];
            }
        }

        // 2. Atividades da Agenda com Data Específica ou Prazo Estipulado
        if (Schema::hasTable('atividades')) {
            $atividadesQuery = DB::table('atividades as a')
                ->leftJoin('unidades as u', 'u.id', '=', 'a.unidade_id')
                ->whereNull('a.deleted_at')
                ->where(function ($q) {
                    $q->whereNotNull('a.data_estipulada_entrega')
                      ->orWhereNotNull('a.data_entrega')
                      ->orWhereNotNull('a.data_inicio');
                });

            if (!$isSede) {
                $atividadesQuery->where(function ($q) use ($unidadeIds, $dto) {
                    $q->whereIn('a.unidade_id', $unidadeIds)
                      ->orWhere('a.usuario_id', $dto->usuarioId);
                });
            }

            $atividades = $atividadesQuery
                ->orderByRaw('COALESCE(a.data_estipulada_entrega, a.data_entrega, a.data_inicio) ASC')
                ->limit(15)
                ->get([
                    'a.id',
                    'a.numero',
                    'a.descricao',
                    'a.status',
                    'a.data_estipulada_entrega',
                    'a.data_entrega',
                    'a.data_inicio',
                    'u.sigla as unidade_sigla',
                ]);

            foreach ($atividades as $atv) {
                $dtRef = $atv->data_estipulada_entrega ?? $atv->data_entrega ?? $atv->data_inicio;
                if (!$dtRef) {
                    continue;
                }
                $dtCarbon = Carbon::parse($dtRef);
                $isConcluido = $atv->status === 'CONCLUIDO';
                $categoria = (!$isConcluido && $dtCarbon->lte($hoje->copy()->addDays(7))) ? 'VENCENDO' : 'PROXIMO';

                $itens[] = [
                    'id' => 'atividade-' . $atv->id,
                    'tipo' => 'ATIVIDADE',
                    'categoria' => $categoria,
                    'titulo' => "[ATV-{$atv->numero}] " . Str::limit(trim((string) $atv->descricao), 110),
                    'unidade' => $atv->unidade_sigla ?: 'Unidade',
                    'prazo' => $dtCarbon->format('d/m/Y'),
                    'data_evento' => $dtCarbon->format('Y-m-d'),
                    'status' => $isConcluido ? 'Concluída' : ($atv->status ?: 'Em andamento'),
                    'pilar' => '1º P: Prazo',
                    'rota' => ['gestao', 'atividade'],
                ];
            }
        }

        // 3. Períodos Avaliativos / Consolidações de Planos de Ação Individuais
        if (Schema::hasTable('planos_trabalhos_consolidacoes') && Schema::hasTable('planos_trabalhos')) {
            $consolidacoesQuery = DB::table('planos_trabalhos_consolidacoes as ptc')
                ->join('planos_trabalhos as pt', 'pt.id', '=', 'ptc.plano_trabalho_id')
                ->leftJoin('unidades as u', 'u.id', '=', 'pt.unidade_id')
                ->whereNull('ptc.deleted_at')
                ->whereNull('pt.deleted_at')
                ->whereNotNull('ptc.data_fim')
                ->where('ptc.status', '!=', 'AVALIADO');

            if (!$isSede) {
                $consolidacoesQuery->where(function ($q) use ($unidadeIds, $dto) {
                    $q->whereIn('pt.unidade_id', $unidadeIds)
                      ->orWhere('pt.usuario_id', $dto->usuarioId);
                });
            }

            $consolidacoes = $consolidacoesQuery
                ->orderBy('ptc.data_fim', 'asc')
                ->limit(10)
                ->get([
                    'ptc.id',
                    'ptc.data_inicio',
                    'ptc.data_fim',
                    'ptc.status',
                    'pt.numero as plano_numero',
                    'u.sigla as unidade_sigla',
                ]);

            foreach ($consolidacoes as $cons) {
                $dtFim = Carbon::parse($cons->data_fim);
                $categoria = $dtFim->lte($hoje->copy()->addDays(10)) ? 'VENCENDO' : 'PROXIMO';

                $itens[] = [
                    'id' => 'consolidacao-' . $cons->id,
                    'tipo' => 'PLANO_INDIVIDUAL',
                    'categoria' => $categoria,
                    'titulo' => "[PAI #{$cons->plano_numero}] Encerramento de período avaliativo do Plano Individual",
                    'unidade' => $cons->unidade_sigla ?: 'Unidade',
                    'prazo' => $dtFim->format('d/m/Y'),
                    'data_evento' => $dtFim->format('Y-m-d'),
                    'status' => $cons->status === 'CONCLUIDO' ? 'Aguardando Avaliação' : 'Em Execução',
                    'pilar' => '1º P: Prazo',
                    'rota' => ['gestao', 'plano-trabalho'],
                ];
            }
        }

        // 4. Marcos dos Projetos Estruturantes (GTs)
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
                $prazoStr = (string) $proj->prazo;
                $prazoLower = mb_strtolower($prazoStr);
                $categoria = 'PROXIMO';
                $dataEvento = null;

                if (str_contains($prazoLower, '30 dias') || str_contains($prazoLower, 'imediato') || str_contains($prazoLower, 'pronto')) {
                    $categoria = 'VENCENDO';
                }

                // Determinar data_evento exata quando houver prazo de vencimento objetivo ou marco no mês
                if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $prazoStr, $m)) {
                    $dataEvento = "{$m[3]}-{$m[2]}-{$m[1]}";
                } elseif (str_contains($prazoLower, 'imediato')) {
                    $dtImediato = $this->ajustarParaDiaUtil(Carbon::create($anoAtual, $mesAtual, 16));
                    $dataEvento = $dtImediato->format('Y-m-d');
                } elseif (str_contains($prazoLower, 'quinzenal')) {
                    $dtQuinz = $this->ajustarParaDiaUtil(Carbon::create($anoAtual, $mesAtual, 20));
                    $dataEvento = $dtQuinz->format('Y-m-d');
                } elseif (str_contains($prazoLower, '30 dias')) {
                    $dt30 = $this->ajustarParaDiaUtil(Carbon::create($anoAtual, $mesAtual, 1)->endOfMonth());
                    $dataEvento = $dt30->format('Y-m-d');
                } elseif (str_contains($prazoLower, '60 dias')) {
                    $dt60 = $this->ajustarParaDiaUtil(Carbon::create($anoAtual, $mesAtual, 1)->addMonth()->endOfMonth());
                    $dataEvento = $dt60->format('Y-m-d');
                } elseif (str_contains($prazoLower, '90 a 120 dias') || str_contains($prazoLower, '90 dias')) {
                    $dt90 = $this->ajustarParaDiaUtil(Carbon::create($anoAtual, $mesAtual, 1)->addMonths(2)->endOfMonth());
                    $dataEvento = $dt90->format('Y-m-d');
                }

                $itens[] = [
                    'id' => 'projeto-' . $proj->id,
                    'tipo' => 'PROJETO',
                    'categoria' => $categoria,
                    'titulo' => "[{$proj->codigo}] {$proj->titulo}",
                    'unidade' => (string) $proj->lider,
                    'prazo' => $prazoStr,
                    'data_evento' => $dataEvento,
                    'status' => (string) $proj->status,
                    'pilar' => '1º P: Prazo',
                    'rota' => ['gestao', 'projeto'],
                ];
            }
        }

        // 5. Iniciativas Prioritárias de Curto Prazo das Unidades no Escopo
        if (Schema::hasTable('sgpe_iniciativas')) {
            $prazosCurtos = ['30 dias', '45 dias', '60 dias', '90 dias'];

            $iniciativasQuery = DB::table('sgpe_iniciativas')
                ->whereIn('prazo', $prazosCurtos);

            if (!$isSede) {
                $iniciativasQuery->whereIn('sigla_unidade', $siglas);
            }

            $iniciativas = $iniciativasQuery
                ->orderByRaw("FIELD(prazo, '30 dias', '45 dias', '60 dias', '90 dias')")
                ->limit(10)
                ->get();

            foreach ($iniciativas as $inic) {
                $prazoInic = (string) $inic->prazo;
                $categoria = in_array($prazoInic, ['30 dias', '45 dias', '60 dias']) ? 'VENCENDO' : 'PROXIMO';

                $dtInic = match ($prazoInic) {
                    '30 dias' => $this->ajustarParaDiaUtil(Carbon::create($anoAtual, $mesAtual, 1)->endOfMonth()),
                    '45 dias' => $this->ajustarParaDiaUtil(Carbon::create($anoAtual, $mesAtual, 1)->addMonth()->day(15)),
                    '60 dias' => $this->ajustarParaDiaUtil(Carbon::create($anoAtual, $mesAtual, 1)->addMonth()->endOfMonth()),
                    '90 dias' => $this->ajustarParaDiaUtil(Carbon::create($anoAtual, $mesAtual, 1)->addMonths(2)->endOfMonth()),
                    default => null,
                };

                $itens[] = [
                    'id' => 'iniciativa-' . $inic->id,
                    'tipo' => 'INICIATIVA',
                    'categoria' => $categoria,
                    'titulo' => (string) $inic->acao,
                    'unidade' => (string) $inic->sigla_unidade,
                    'prazo' => $dtInic ? ($dtInic->format('d/m/Y') . " ({$prazoInic})") : $prazoInic,
                    'data_evento' => $dtInic ? $dtInic->format('Y-m-d') : null,
                    'status' => (string) $inic->status_execucao,
                    'pilar' => (string) $inic->pilar_3ps,
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
