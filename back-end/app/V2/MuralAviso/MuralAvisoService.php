<?php

declare(strict_types=1);

namespace App\V2\MuralAviso;

use App\Enums\MuralAvisoDestinatario;
use App\Enums\MuralAvisoRemetenteTipo;
use App\Exceptions\NotFoundException;
use App\Models\MuralAviso;
use App\Repository\MuralAviso\MuralAvisoRepository;
use App\Repository\MuralAvisoLeitura\MuralAvisoLeituraRepository;
use App\Repository\TenantRepository;
use App\V2\MuralAviso\DTOs\MuralAvisoDestroyDTO;
use App\V2\MuralAviso\DTOs\MuralAvisoPendenteDTO;
use App\V2\MuralAviso\DTOs\MuralAvisoQueryDTO;
use App\V2\MuralAviso\DTOs\MuralAvisoStoreDTO;
use App\V2\MuralAviso\Validators\MuralAvisoAuthorizationValidator;
use App\V2\MuralAviso\Validators\MuralAvisoStoreValidator;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MuralAvisoService
{
    public function __construct(
        private readonly MuralAvisoRepository $repository,
        private readonly MuralAvisoLeituraRepository $leituraRepository,
        private readonly TenantRepository $tenantRepository,
        private readonly MuralAvisoStoreValidator $storeValidator,
        private readonly MuralAvisoAuthorizationValidator $authorizationValidator,
    ) {}

    public function index(MuralAvisoQueryDTO $dto): LengthAwarePaginator
    {
        return $this->repository->paginateForPainel($dto->getFilterTenantIds(), $dto->perPage);
    }

    public function show(string $id): MuralAviso
    {
        $aviso = $this->repository->findById($id);

        if ($aviso === null) {
            throw new NotFoundException('Aviso não encontrado.');
        }

        $aviso->load(['tenant', 'publicadoPor']);

        return $aviso;
    }

    public function store(MuralAvisoStoreDTO $dto): MuralAviso
    {
        $this->storeValidator->validar($dto);

        return $this->repository->create([
            'titulo' => $dto->titulo,
            'conteudo' => $dto->conteudo,
            'destinatario' => $dto->destinatario,
            'tenant_id' => $dto->destinatario === MuralAvisoDestinatario::TODOS->value ? null : $dto->tenantId,
            'remetente_tipo' => $dto->remetenteTipo()->value,
            'remetente_tenant_id' => $dto->remetenteTenantId(),
            'publicado_por_user_panel_id' => $dto->usuarioId,
            'data_publicacao' => $dto->dataPublicacao,
            'data_expiracao' => $dto->dataExpiracao,
        ]);
    }

    public function publicarDiretoTenant(string $tenantId, string $titulo, string $conteudo, ?string $dataExpiracao = null): MuralAviso
    {
        $centralConn = env('DB_CONNECTION', 'mysql');
        $panelUser = DB::connection($centralConn)->table('users_panel')->first();
        if (!$panelUser) {
            $panelUserId = DB::connection($centralConn)->table('users_panel')->insertGetId([
                'nome' => 'Administrador Institucional PGE-MA',
                'email' => 'admin@pge.ma.gov.br',
                'cpf' => '00000000000',
                'password' => bcrypt(Str::random(16)),
                'nivel' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $panelUserId = $panelUser->id;
        }

        $dtPublicacao = now();
        $dtExpiracao = !empty($dataExpiracao)
            ? Carbon::parse($dataExpiracao)->endOfDay()
            : now()->addDays(60)->endOfDay();

        return $this->repository->create([
            'titulo' => trim($titulo),
            'conteudo' => trim($conteudo),
            'destinatario' => MuralAvisoDestinatario::TENANT_ESPECIFICO->value,
            'tenant_id' => $tenantId,
            'remetente_tipo' => MuralAvisoRemetenteTipo::TENANT->value,
            'remetente_tenant_id' => $tenantId,
            'publicado_por_user_panel_id' => $panelUserId,
            'data_publicacao' => $dtPublicacao,
            'data_expiracao' => $dtExpiracao,
        ]);
    }

    public function excluirDiretoTenant(string $id, string $tenantId): void
    {
        $aviso = $this->repository->findById($id);
        if ($aviso !== null && ($aviso->tenant_id === $tenantId || $aviso->remetente_tenant_id === $tenantId || $aviso->destinatario === MuralAvisoDestinatario::TODOS->value)) {
            $this->repository->delete($id);
        }
    }

    public function update(string $id, MuralAvisoStoreDTO $dto): MuralAviso
    {
        $this->authorizationValidator->validar($id, $dto->nivelUsuario, $dto->tenantIds);

        $this->storeValidator->validar($dto, $id);

        /** @var MuralAviso */
        return $this->repository->update($id, [
            'titulo' => $dto->titulo,
            'conteudo' => $dto->conteudo,
            'destinatario' => $dto->destinatario,
            'tenant_id' => $dto->destinatario === MuralAvisoDestinatario::TODOS->value ? null : $dto->tenantId,
            'remetente_tipo' => $dto->remetenteTipo()->value,
            'remetente_tenant_id' => $dto->remetenteTenantId(),
            'data_publicacao' => $dto->dataPublicacao,
            'data_expiracao' => $dto->dataExpiracao,
        ]);
    }

    public function destroy(MuralAvisoDestroyDTO $dto): void
    {
        $this->authorizationValidator->validar($dto->id, $dto->nivelUsuario, $dto->tenantIds);

        $this->repository->delete($dto->id);
    }

    /**
     * @return list<MuralAvisoPendenteDTO>
     */
    public function vigentes(string $usuarioId, string $tenantId, bool $incluirLidos): array
    {
        $leitura = $this->leituraRepository->findByUsuarioId($usuarioId);
        $dataConfirmacao = $leitura?->data_confirmacao;

        $avisos = $this->repository->findVigentes($tenantId);

        $dtos = [];

        foreach ($avisos as $aviso) {
            $lido = $this->isLido($aviso['data_publicacao'], $dataConfirmacao);

            if (!$incluirLidos && $lido) {
                continue;
            }

            $dtos[] = MuralAvisoPendenteDTO::fromArray([
                'id' => $aviso['id'],
                'titulo' => $aviso['titulo'],
                'conteudo' => $aviso['conteudo'],
                'remetente' => $this->resolveRemetente($aviso),
                'data_publicacao' => $aviso['data_publicacao'],
                'lido' => $lido,
            ]);
        }

        usort($dtos, fn (MuralAvisoPendenteDTO $a, MuralAvisoPendenteDTO $b) => $a->lido <=> $b->lido);

        return $dtos;
    }

    /**
     * Retorna os alertas automáticos de ações pendentes e prazos expirando do usuário,
     * respeitando as configurações de notificações definidas no menu Preferências.
     */
    public function obterAlertasPendentesUsuario(string $usuarioId): array
    {
        $usuario = DB::table('usuarios')->where('id', $usuarioId)->first(['id', 'perfil_id', 'notificacoes']);
        if (!$usuario) {
            return ['habilitado' => true, 'itens' => []];
        }

        $notifConfig = [];
        if (!empty($usuario->notificacoes)) {
            $decoded = is_string($usuario->notificacoes) ? json_decode($usuario->notificacoes, true) : (array) $usuario->notificacoes;
            if (is_array($decoded)) {
                $notifConfig = $decoded;
            }
        }

        $enviarPetrvs = array_key_exists('enviar_petrvs', $notifConfig) ? (bool) $notifConfig['enviar_petrvs'] : true;
        if (!$enviarPetrvs) {
            return ['habilitado' => false, 'itens' => []];
        }

        $naoNotificar = isset($notifConfig['nao_notificar']) && is_array($notifConfig['nao_notificar'])
            ? $notifConfig['nao_notificar']
            : [];

        $itens = [];
        $hoje = now();
        $meses = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
        $nomeMes = $meses[$hoje->month] ?? '';

        // 1. Relatório Mensal da Corregedoria
        if (!in_array('PRAZO_RELATORIO_CORREGEDORIA', $naoNotificar, true) && Schema::hasTable('relatorios_corregedoria')) {
            $rel = DB::table('relatorios_corregedoria')
                ->where('usuario_id', $usuarioId)
                ->where('mes', $hoje->month)
                ->where('ano', $hoje->year)
                ->whereNull('deleted_at')
                ->first();

            $statusRel = $rel ? $rel->status : 'NAO_INICIADO';
            if (!in_array($statusRel, ['CONCLUIDO_ASSINADO', 'HOMOLOGADO'], true)) {
                $quintoDiaUtil = $this->calcularQuintoDiaUtil($hoje->year, $hoje->month)->format('d/m/Y');
                $labelStatus = $statusRel === 'RASCUNHO' ? 'Em rascunho' : ($statusRel === 'NAO_INICIADO' ? 'Não iniciado' : $statusRel);
                $itens[] = [
                    'id' => 'alerta-relatorio-corregedoria',
                    'codigo' => 'PRAZO_RELATORIO_CORREGEDORIA',
                    'origem' => 'Corregedoria-Geral',
                    'titulo' => "Relatório Mensal de Atividades ({$nomeMes}/{$hoje->year})",
                    'descricao' => "Situação atual: {$labelStatus}. Pendente de consolidação e assinatura digital.",
                    'prazo' => "Até {$quintoDiaUtil} (5º dia útil)",
                    'rota' => ['gestao', 'corregedoria', 'meu-relatorio'],
                ];
            }
        }

        // 2. Homologações da Corregedoria (apenas para perfil Admin ou Corregedoria)
        if (!in_array('HOMOLOGACAO_CORREGEDORIA', $naoNotificar, true) && Schema::hasTable('relatorios_corregedoria')) {
            $nivelPerfil = DB::table('perfis')->where('id', $usuario->perfil_id)->value('nivel');
            $acessoCorregedoria = ($nivelPerfil !== null && in_array((int) $nivelPerfil, [0, 1, 2], true));
            if (!$acessoCorregedoria && Schema::hasTable('usuarios_integrantes') && Schema::hasTable('unidades')) {
                $acessoCorregedoria = DB::table('usuarios_integrantes as ui')
                    ->join('unidades as un', 'un.id', '=', 'ui.unidade_id')
                    ->where('ui.usuario_id', $usuarioId)
                    ->whereNull('ui.deleted_at')
                    ->where(function ($q) {
                        $q->where('un.sigla', 'CG')
                          ->orWhere('un.sigla', 'LIKE', 'CG-%')
                          ->orWhere('un.nome', 'LIKE', '%Corregedoria%');
                    })
                    ->exists();
            }

            if ($acessoCorregedoria) {
                $pendentesHomologacao = DB::table('relatorios_corregedoria')
                    ->where('status', 'CONCLUIDO_ASSINADO')
                    ->whereNull('deleted_at')
                    ->count();

                if ($pendentesHomologacao > 0) {
                    $itens[] = [
                        'id' => 'alerta-homologacao-corregedoria',
                        'codigo' => 'HOMOLOGACAO_CORREGEDORIA',
                        'origem' => 'Corregedoria-Geral',
                        'titulo' => 'Relatórios Mensais Aguardando Homologação',
                        'descricao' => "Existem {$pendentesHomologacao} relatório(s) assinado(s) aguardando análise no Painel da Corregedoria.",
                        'prazo' => "Competência {$nomeMes}/{$hoje->year}",
                        'rota' => ['gestao', 'corregedoria', 'painel'],
                    ];
                }
            }
        }

        // 3. Atividades da Agenda de Tarefas e Planos Individuais
        if (!in_array('PRAZO_ATIVIDADES_PLANOS', $naoNotificar, true)) {
            if (Schema::hasTable('atividades')) {
                $atvPendentes = DB::table('atividades')
                    ->where('usuario_id', $usuarioId)
                    ->whereNull('deleted_at')
                    ->whereIn('status', ['INICIADO', 'PAUSADO', 'NAO_INICIADO'])
                    ->count();

                if ($atvPendentes > 0) {
                    $itens[] = [
                        'id' => 'alerta-atividades-andamento',
                        'codigo' => 'PRAZO_ATIVIDADES_PLANOS',
                        'origem' => 'Agenda de Tarefas',
                        'titulo' => "Atividades em Curso na Agenda de Tarefas ({$atvPendentes})",
                        'descricao' => "Você possui {$atvPendentes} atividade(s) registrada(s) pendente(s) de conclusão.",
                        'prazo' => 'Mês vigente',
                        'rota' => ['gestao', 'atividade'],
                    ];
                }
            }

            if (Schema::hasTable('planos_trabalhos_consolidacoes') && Schema::hasTable('planos_trabalhos')) {
                $consolidacao = DB::table('planos_trabalhos_consolidacoes as ptc')
                    ->join('planos_trabalhos as pt', 'pt.id', '=', 'ptc.plano_trabalho_id')
                    ->where('pt.usuario_id', $usuarioId)
                    ->whereNull('ptc.deleted_at')
                    ->whereNull('pt.deleted_at')
                    ->where('ptc.status', 'INCLUIDO')
                    ->orderBy('ptc.data_fim', 'asc')
                    ->first(['ptc.id', 'ptc.data_fim', 'pt.numero']);

                if ($consolidacao) {
                    $dtFimFmt = Carbon::parse($consolidacao->data_fim)->format('d/m/Y');
                    $itens[] = [
                        'id' => 'alerta-consolidacao-' . $consolidacao->id,
                        'codigo' => 'PRAZO_ATIVIDADES_PLANOS',
                        'origem' => 'Plano de Ação Individual',
                        'titulo' => "Período Avaliativo do Plano Individual #{$consolidacao->numero}",
                        'descricao' => 'Período aberto para registro de execução das metas e entregas pactuadas.',
                        'prazo' => "Até {$dtFimFmt}",
                        'rota' => ['gestao', 'plano-trabalho-v2'],
                    ];
                }
            }
        }

        // 4. Iniciativas Prioritárias de Curto Prazo (30 e 45 dias) da Unidade
        if (!in_array('PRAZO_INICIATIVAS_MARCOS', $naoNotificar, true) && Schema::hasTable('sgpe_iniciativas')) {
            $siglasUsuario = [];
            if (Schema::hasTable('usuarios_integrantes') && Schema::hasTable('unidades')) {
                $siglasUsuario = DB::table('usuarios_integrantes as ui')
                    ->join('unidades as un', 'un.id', '=', 'ui.unidade_id')
                    ->where('ui.usuario_id', $usuarioId)
                    ->whereNull('ui.deleted_at')
                    ->whereNull('un.deleted_at')
                    ->pluck('un.sigla')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
            }

            $iniQuery = DB::table('sgpe_iniciativas')
                ->whereIn('prazo', ['30 dias', '45 dias']);

            if (!empty($siglasUsuario) && !in_array('PGE-MA', $siglasUsuario, true) && !in_array('SEDE', $siglasUsuario, true)) {
                $iniQuery->whereIn('sigla_unidade', $siglasUsuario);
            }

            $iniciativasCurtoPrazo = $iniQuery
                ->orderByRaw("FIELD(prazo, '30 dias', '45 dias')")
                ->limit(4)
                ->get(['id', 'sigla_unidade', 'kr_codigo', 'acao', 'prazo', 'status_execucao']);

            foreach ($iniciativasCurtoPrazo as $ini) {
                $itens[] = [
                    'id' => 'alerta-ini-' . $ini->id,
                    'codigo' => 'PRAZO_INICIATIVAS_MARCOS',
                    'origem' => $ini->sigla_unidade ?: 'PGE-MA',
                    'titulo' => '[' . ($ini->kr_codigo ?: 'META') . '] ' . Str::limit((string) $ini->acao, 95),
                    'descricao' => 'Iniciativa prioritária de curto prazo (' . ($ini->status_execucao ?: 'Não Iniciado') . ').',
                    'prazo' => (string) $ini->prazo,
                    'rota' => ['gestao', 'planejamento'],
                ];
            }
        }

        return [
            'habilitado' => true,
            'itens' => $itens,
        ];
    }

    private function calcularQuintoDiaUtil(int $ano, int $mes): Carbon
    {
        $data = Carbon::create($ano, $mes, 1)->startOfDay();
        $diasUteis = 0;

        while ($diasUteis < 5) {
            if (!$data->isWeekend()) {
                $diasUteis++;
                if ($diasUteis === 5) {
                    break;
                }
            }
            $data->addDay();
        }

        return $data;
    }

    private function isLido(string $dataPublicacao, ?\DateTimeInterface $dataConfirmacao): bool
    {
        if ($dataConfirmacao === null) {
            return false;
        }

        return new \DateTimeImmutable($dataPublicacao) <= $dataConfirmacao;
    }

    public function confirmarLeitura(string $usuarioId): void
    {
        $this->leituraRepository->upsert($usuarioId, now());
    }

    /**
     * @param array<string, mixed> $aviso
     */
    private function resolveRemetente(array $aviso): string
    {
        if ($aviso['remetente_tipo'] === MuralAvisoRemetenteTipo::ORGAO_CENTRAL->value) {
            return 'Administração Central';
        }

        if (!empty($aviso['remetente_tenant_id'])) {
            if (strtolower((string) $aviso['remetente_tenant_id']) === 'pgema') {
                return 'Gabinete / Administração PGE-MA';
            }
            $tenant = $this->tenantRepository->findById($aviso['remetente_tenant_id']);
            return $tenant?->id ?? 'Administração';
        }

        return 'Sistema';
    }
}
