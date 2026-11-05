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

    public function getData(HomeRequestDTO $dto): array
    {
        $escopo = $this->resolverUnidades($dto);
        $subordinadasIds = array_slice($escopo, 1);

        $corregedoria = $this->verificarPendenciasCorregedoria($dto->usuarioId);
        $resumoIndividual = $this->obterResumoIndividual($dto->usuarioId);

        return array_merge([
            'assinaturas_pe_pendentes' => $this->planoEntregaRepository->countPlanosEntregaHomologacao($subordinadasIds),
            'assinaturas_pt_pendentes' => $this->planoTrabalhoRepository->countPlanosTrabalhoAssinatura($escopo, $dto->usuarioId),
            'registros_execucao_pe_atraso' => $this->planoEntregaRepository->countEntregasSemProgresso([$dto->unidadeId], PlanoEntrega::DATA_MUDANCA_REGRA_PE),
            'registros_execucao_pt_atraso' => $this->consolidacaoRepository->countConsolidacoesAtrasadas($dto->usuarioId, [$dto->unidadeId]),
            'avaliacoes_pt_pendentes' => $this->planoTrabalhoRepository->countAguardandoMinhaAvaliacao($escopo, $dto->usuarioId),
            'avaliacoes_pe_pendentes' => $this->planoEntregaRepository->countPlanosEntregaAvaliacao($subordinadasIds, PlanoEntrega::DATA_MUDANCA_REGRA_PE),
            'resumo_individual' => $resumoIndividual,
        ], $corregedoria);
    }

    public function getDataGlobal(string $usuarioId): array
    {
        $unidadesGerenciadas = $this->unidadeRepository->getUnidadesGerenciadas($usuarioId);
        $unidadeIds = $unidadesGerenciadas->pluck('id')->toArray();

        $reAtrasadosProprios = $this->aguardandoRegistroExecucao->count($usuarioId);
        $corregedoria = $this->verificarPendenciasCorregedoria($usuarioId);
        $resumoIndividual = $this->obterResumoIndividual($usuarioId);

        if (empty($unidadeIds)) {
            return array_merge([
                'assinaturas_pe_pendentes' => 0,
                'assinaturas_pt_pendentes' => 0,
                'registros_execucao_pe_atraso' => 0,
                'registros_execucao_pt_atraso' => $reAtrasadosProprios,
                'avaliacoes_pt_pendentes' => 0,
                'avaliacoes_pe_pendentes' => 0,
                'resumo_individual' => $resumoIndividual,
            ], $corregedoria);
        }

        return array_merge([
            'assinaturas_pe_pendentes' => $this->homologacaoPendentePE->count($usuarioId),
            'assinaturas_pt_pendentes' => $this->aguardandoAssinatura->count($usuarioId),
            'registros_execucao_pe_atraso' => $this->registroExecucaoAtrasoPE->count($usuarioId),
            'registros_execucao_pt_atraso' => $reAtrasadosProprios,
            'avaliacoes_pt_pendentes' => $this->aguardandoAvaliacao->count($usuarioId),
            'avaliacoes_pe_pendentes' => $this->avaliacaoPendentePE->count($usuarioId),
            'resumo_individual' => $resumoIndividual,
        ], $corregedoria);
    }

    /**
     * Verifica se o usuário possui perfil de administração ou vínculo com a Corregedoria-Geral (CG).
     */
    private function possuiAcessoCorregedoria(string $usuarioId): bool
    {
        $nivelPerfil = DB::table('usuarios as u')
            ->join('perfis as p', 'p.id', '=', 'u.perfil_id')
            ->where('u.id', $usuarioId)
            ->value('p.nivel');

        if ($nivelPerfil !== null && in_array((int) $nivelPerfil, [0, 1, 2], true)) {
            return true;
        }

        if (Schema::hasTable('usuarios_integrantes') && Schema::hasTable('unidades')) {
            $vinculadoCorregedoria = DB::table('usuarios_integrantes as ui')
                ->join('unidades as un', 'un.id', '=', 'ui.unidade_id')
                ->where('ui.usuario_id', $usuarioId)
                ->whereNull('ui.deleted_at')
                ->whereNull('un.deleted_at')
                ->where(function ($q) {
                    $q->where('un.sigla', 'CG')
                      ->orWhere('un.sigla', 'LIKE', 'CG-%')
                      ->orWhere('un.nome', 'LIKE', '%Corregedoria%');
                })
                ->exists();

            if ($vinculadoCorregedoria) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica pendências do Relatório Mensal de Atividades e homologações da Corregedoria.
     */
    private function verificarPendenciasCorregedoria(string $usuarioId): array
    {
        $acessoCorregedoria = $this->possuiAcessoCorregedoria($usuarioId);

        if (!Schema::hasTable('relatorios_corregedoria')) {
            return [
                'relatorio_mensal' => null,
                'relatorios_aguardando_homologacao' => 0,
                'acesso_corregedoria' => $acessoCorregedoria,
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
        $pendente = !in_array($status, ['CONCLUIDO_ASSINADO', 'HOMOLOGADO'], true);

        $aguardandoHomologacao = 0;
        if ($acessoCorregedoria) {
            $aguardandoHomologacao = DB::table('relatorios_corregedoria')
                ->where('status', 'CONCLUIDO_ASSINADO')
                ->whereNull('deleted_at')
                ->count();
        }

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
            'acesso_corregedoria' => $acessoCorregedoria,
        ];
    }

    /**
     * Retorna resumo do Plano de Ação Individual e da Agenda de Tarefas (Atividades) do usuário.
     */
    private function obterResumoIndividual(string $usuarioId): array
    {
        $planoNumero = null;
        $planoStatus = null;
        $totalEntregasPlano = 0;

        if (Schema::hasTable('planos_trabalhos')) {
            $plano = DB::table('planos_trabalhos')
                ->where('usuario_id', $usuarioId)
                ->whereNull('deleted_at')
                ->orderByRaw("status = 'ATIVO' desc")
                ->orderByDesc('numero')
                ->first(['id', 'numero', 'status']);

            if ($plano) {
                $planoNumero = (int) $plano->numero;
                $planoStatus = (string) $plano->status;
                if (Schema::hasTable('planos_trabalhos_entregas')) {
                    $totalEntregasPlano = DB::table('planos_trabalhos_entregas')
                        ->where('plano_trabalho_id', $plano->id)
                        ->whereNull('deleted_at')
                        ->count();
                }
            }
        }

        $atividadesAndamento = 0;
        $atividadesConcluidas = 0;
        if (Schema::hasTable('atividades')) {
            $atividadesAndamento = DB::table('atividades')
                ->where('usuario_id', $usuarioId)
                ->whereNull('deleted_at')
                ->whereIn('status', ['INICIADO', 'PAUSADO', 'NAO_INICIADO'])
                ->count();

            $atividadesConcluidas = DB::table('atividades')
                ->where('usuario_id', $usuarioId)
                ->whereNull('deleted_at')
                ->where('status', 'CONCLUIDO')
                ->count();
        }

        return [
            'plano_numero' => $planoNumero,
            'plano_status' => $planoStatus,
            'total_entregas_plano' => $totalEntregasPlano,
            'atividades_andamento' => $atividadesAndamento,
            'atividades_concluidas' => $atividadesConcluidas,
        ];
    }
}
