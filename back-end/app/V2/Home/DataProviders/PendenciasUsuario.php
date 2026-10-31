<?php

declare(strict_types=1);

namespace App\V2\Home\DataProviders;

use App\Models\PlanoEntrega;
use App\Repository\PlanoEntregaRepository;
use App\Repository\PlanoTrabalhoConsolidacaoRepository;
use App\Repository\PlanoTrabalhoRepository;
use App\Repository\UnidadeRepository;
use App\V2\PlanoEntrega\DataProviders\AvaliacaoPendentePEDataProvider;
use App\V2\PlanoEntrega\DataProviders\HomologacaoPendentePEDataProvider;
use App\V2\PlanoEntrega\DataProviders\RegistroExecucaoAtrasoPEDataProvider;
use App\V2\Home\DTOs\HomeRequestDTO;
use App\V2\Home\Traits\ResolveUnidades;
use App\V2\PlanoTrabalho\DataProviders\AguardandoMinhaAssinaturaDataProvider;
use App\V2\PlanoTrabalho\DataProviders\AguardandoMinhaAvaliacaoDataProvider;
use App\V2\PlanoTrabalho\DataProviders\AguardandoMeuRegistroExecucaoDataProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PendenciasUsuario
{
    use ResolveUnidades;

    public function __construct(
        private readonly UnidadeRepository $unidadeRepository,
        private readonly PlanoTrabalhoRepository $planoTrabalhoRepository,
        private readonly PlanoTrabalhoConsolidacaoRepository $consolidacaoRepository,
        private readonly PlanoEntregaRepository $planoEntregaRepository,
        private readonly AguardandoMinhaAssinaturaDataProvider $aguardandoAssinatura,
        private readonly AguardandoMinhaAvaliacaoDataProvider $aguardandoAvaliacao,
        private readonly AguardandoMeuRegistroExecucaoDataProvider $aguardandoRegistroExecucao,
        private readonly AvaliacaoPendentePEDataProvider $avaliacaoPendentePE,
        private readonly HomologacaoPendentePEDataProvider $homologacaoPendentePE,
        private readonly RegistroExecucaoAtrasoPEDataProvider $registroExecucaoAtrasoPE,
    ) {}

    protected function getUnidadeRepository(): UnidadeRepository
    {
        return $this->unidadeRepository;
    }

    /**
     * @return array{
     *   assinaturas_pe_pendentes: int,
     *   assinaturas_pt_pendentes: int,
     *   registros_execucao_pe_atraso: int,
     *   registros_execucao_pt_atraso: int,
     *   avaliacoes_pt_pendentes: int,
     *   avaliacoes_pe_pendentes: int,
     *   relatorio_mensal?: array{status: string, pendente: bool, mes: int, ano: int, relatorio_id: ?string, total_atos: int},
     *   relatorios_aguardando_homologacao?: int,
     * }
     */
    public function getData(HomeRequestDTO $dto): array
    {
        $escopo = $this->resolverUnidades($dto);
        $subordinadasIds = array_slice($escopo, 1); // escopo sem a unidade raiz

        $corregedoria = $this->verificarPendenciasCorregedoria($dto->usuarioId);

        return array_merge([
            'assinaturas_pe_pendentes' => $this->planoEntregaRepository->countPlanosEntregaHomologacao($subordinadasIds),
            'assinaturas_pt_pendentes' => $this->planoTrabalhoRepository->countPlanosTrabalhoAssinatura($escopo, $dto->usuarioId),
            'registros_execucao_pe_atraso' => $this->planoEntregaRepository->countEntregasSemProgresso([$dto->unidadeId], PlanoEntrega::DATA_MUDANCA_REGRA_PE),
            'registros_execucao_pt_atraso' => $this->consolidacaoRepository->countConsolidacoesAtrasadas($dto->usuarioId, [$dto->unidadeId]),
            'avaliacoes_pt_pendentes' => $this->planoTrabalhoRepository->countAguardandoMinhaAvaliacao($escopo, $dto->usuarioId),
            'avaliacoes_pe_pendentes' => $this->planoEntregaRepository->countPlanosEntregaAvaliacao($subordinadasIds, PlanoEntrega::DATA_MUDANCA_REGRA_PE),
        ], $corregedoria);
    }

    /**
     * Calcula pendências somando todas as unidades onde o usuário é diretamente chefia (titular ou substituto).
     *
     * @return array{
     *   assinaturas_pe_pendentes: int,
     *   assinaturas_pt_pendentes: int,
     *   registros_execucao_pe_atraso: int,
     *   registros_execucao_pt_atraso: int,
     *   avaliacoes_pt_pendentes: int,
     *   avaliacoes_pe_pendentes: int,
     *   relatorio_mensal?: array{status: string, pendente: bool, mes: int, ano: int, relatorio_id: ?string, total_atos: int},
     *   relatorios_aguardando_homologacao?: int,
     * }
     */
    public function getDataGlobal(string $usuarioId): array
    {
        $unidadesGerenciadas = $this->unidadeRepository->getUnidadesGerenciadas($usuarioId);
        $unidadeIds = $unidadesGerenciadas->pluck('id')->toArray();

        $reAtrasadosProprios = $this->aguardandoRegistroExecucao->count($usuarioId);
        $corregedoria = $this->verificarPendenciasCorregedoria($usuarioId);

        if (empty($unidadeIds)) {
            return array_merge([
                'assinaturas_pe_pendentes' => 0,
                'assinaturas_pt_pendentes' => 0,
                'registros_execucao_pe_atraso' => 0,
                'registros_execucao_pt_atraso' => $reAtrasadosProprios,
                'avaliacoes_pt_pendentes' => 0,
                'avaliacoes_pe_pendentes' => 0,
            ], $corregedoria);
        }

        return array_merge([
            'assinaturas_pe_pendentes' => $this->homologacaoPendentePE->count($usuarioId),
            'assinaturas_pt_pendentes' => $this->aguardandoAssinatura->count($usuarioId),
            'registros_execucao_pe_atraso' => $this->registroExecucaoAtrasoPE->count($usuarioId),
            'registros_execucao_pt_atraso' => $reAtrasadosProprios,
            'avaliacoes_pt_pendentes' => $this->aguardandoAvaliacao->count($usuarioId),
            'avaliacoes_pe_pendentes' => $this->avaliacaoPendentePE->count($usuarioId),
        ], $corregedoria);
    }

    /**
     * Verifica pendências do Relatório Mensal de Atividades e homologações da Corregedoria.
     */
    private function verificarPendenciasCorregedoria(string $usuarioId): array
    {
        if (!Schema::hasTable('relatorios_corregedoria')) {
            return [
                'relatorio_mensal' => null,
                'relatorios_aguardando_homologacao' => 0,
            ];
        }

        $hoje = now();
        $mes = $hoje->month;
        $ano = $hoje->year;

        $relatorio = DB::table('relatorios_corregedoria')
            ->where('usuario_id', $usuarioId)
            ->where('mes', $mes)
            ->where('ano', $ano)
            ->whereNull('deleted_at')
            ->first();

        $totalAtos = 0;
        if ($relatorio && Schema::hasTable('relatorios_corregedoria_atos')) {
            $totalAtos = DB::table('relatorios_corregedoria_atos')
                ->where('relatorio_corregedoria_id', $relatorio->id)
                ->whereNull('deleted_at')
                ->count();
        }

        $status = $relatorio ? $relatorio->status : 'NAO_INICIADO';
        $pendente = !in_array($status, ['CONCLUIDO_ASSINADO', 'HOMOLOGADO']);

        $aguardandoHomologacao = DB::table('relatorios_corregedoria')
            ->where('status', 'CONCLUIDO_ASSINADO')
            ->whereNull('deleted_at')
            ->count();

        return [
            'relatorio_mensal' => [
                'status' => $status,
                'pendente' => $pendente,
                'mes' => $mes,
                'ano' => $ano,
                'relatorio_id' => $relatorio?->id,
                'total_atos' => $totalAtos,
            ],
            'relatorios_aguardando_homologacao' => $aguardandoHomologacao,
        ];
    }
}
