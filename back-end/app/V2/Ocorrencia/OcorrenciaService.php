<?php

declare(strict_types=1);

namespace App\V2\Ocorrencia;

use App\Exceptions\ValidateException;
use App\Models\Afastamento;
use App\Repository\Afastamento\AfastamentoRepository;
use App\Repository\PlanoTrabalhoConsolidacaoRepository;
use App\Repository\PlanoTrabalhoRepository;
use App\Repository\UnidadeRepository;
use App\Repository\UsuarioRepository;
use App\V2\Ocorrencia\DTOs\OcorrenciaIndexDTO;
use App\V2\Ocorrencia\DTOs\OcorrenciaImpactoDTO;
use App\V2\Ocorrencia\DTOs\OcorrenciaOperacaoDTO;
use App\V2\Ocorrencia\DTOs\OcorrenciaStoreDTO;
use App\V2\Ocorrencia\Validators\OcorrenciaStoreValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OcorrenciaService
{
    private const PRAZO_MAXIMO_EXCLUSAO_DIAS = 365;

    public function __construct(
        private readonly OcorrenciaStoreValidator $validator,
        private readonly OcorrenciaImpactoPolicy $impactoPolicy,
        private readonly AfastamentoRepository $afastamentoRepository,
        private readonly PlanoTrabalhoRepository $planoTrabalhoRepository,
        private readonly PlanoTrabalhoConsolidacaoRepository $consolidacaoRepository,
        private readonly UnidadeRepository $unidadeRepository,
        private readonly UsuarioRepository $usuarioRepository,
    ) {}

    public function agentes(?string $termo = null, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $usuarioLogadoId = Auth::id();
        $unidadeIds = $this->unidadeRepository->getGerenciadasComSubordinadasIds($usuarioLogadoId);

        return $this->usuarioRepository->findAgentesVisiveis($usuarioLogadoId, $unidadeIds, $termo, $page, $perPage);
    }

    public function index(array $data): LengthAwarePaginator
    {
        $usuarioLogadoId = Auth::id();
        $unidadeIds = $this->unidadeRepository->getGerenciadasComSubordinadasIds($usuarioLogadoId);
        $dto = OcorrenciaIndexDTO::fromRequest($data, $usuarioLogadoId, $unidadeIds);

        return $this->afastamentoRepository->buscarOcorrenciasListagem($dto);
    }

    public function store(OcorrenciaStoreDTO $dto): Afastamento
    {
        $this->validator->validarAutorizacao($dto->usuarioId, Auth::id());

        $afastamento = $this->afastamentoRepository->insert($dto->toPersistArray());

        return $afastamento->load('tipoMotivoAfastamento:id,nome,horas');
    }

    public function destroy(string $ocorrenciaId, string $usuarioId): void
    {
        $this->validator->validarAutorizacao($usuarioId, Auth::id());
        $afastamento = $this->validator->validarExistencia($ocorrenciaId, $usuarioId);

        if ($afastamento->created_at->diffInDays(now()) > self::PRAZO_MAXIMO_EXCLUSAO_DIAS) {
            throw new ValidateException('Ocorrência cadastrada há mais de 1 ano não pode ser excluída.');
        }

        DB::transaction(function () use ($afastamento) {
            $this->consolidacaoRepository->deleteAfastamentoVinculos($afastamento->id);
            $this->afastamentoRepository->destroy($afastamento->id);
        });
    }

    public function impactoConsolidacoes(OcorrenciaOperacaoDTO $dto): OcorrenciaImpactoDTO
    {
        if ($dto->isExclusao() && $dto->ocorrenciaId) {
            $afastamento = $this->afastamentoRepository->findById($dto->ocorrenciaId);

            if ($afastamento && $afastamento->created_at->diffInDays(now()) > self::PRAZO_MAXIMO_EXCLUSAO_DIAS) {
                return OcorrenciaImpactoDTO::bloqueada();
            }
        }

        return $this->impactoPolicy->calcularImpacto($dto);
    }

    public function indexProjetosPlanos(array $data): LengthAwarePaginator
    {
        $query = \App\Models\Ocorrencia::with([
            'usuario:id,nome,email',
            'projeto:id,nome',
            'planoEntrega:id,nome,unidade_id',
            'planoTrabalho:id,usuario_id',
        ])->orderBy('created_at', 'desc');

        if (!empty($data['tipo'])) {
            $query->where('tipo', $data['tipo']);
        }
        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (!empty($data['projeto_id'])) {
            $query->where('projeto_id', $data['projeto_id']);
        }
        if (!empty($data['plano_entrega_id'])) {
            $query->where('plano_entrega_id', $data['plano_entrega_id']);
        }

        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['size'] ?? 15);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function storeProjetoPlano(array $data): \App\Models\Ocorrencia
    {
        $ocorrencia = new \App\Models\Ocorrencia();
        $ocorrencia->id = (string) \Illuminate\Support\Str::uuid();
        $ocorrencia->usuario_id = Auth::id() ?? $data['usuario_id'] ?? '52d78c7d-e0c1-422b-b094-2ca5958d5ac1';
        $ocorrencia->tipo = $data['tipo'] ?? 'PROJETO';
        $ocorrencia->titulo = $data['titulo'];
        $ocorrencia->motivo = $data['motivo'] ?? 'Outros';
        $ocorrencia->descricao = $data['descricao'] ?? $data['titulo'];
        $ocorrencia->impacto = $data['impacto'] ?? null;
        $ocorrencia->novo_prazo_proposto = !empty($data['novo_prazo_proposto']) ? $data['novo_prazo_proposto'] : null;
        $ocorrencia->acao_mitigatoria = $data['acao_mitigatoria'] ?? null;
        $ocorrencia->status = $data['status'] ?? 'PENDENTE';
        $ocorrencia->projeto_id = $data['projeto_id'] ?? null;
        $ocorrencia->plano_entrega_id = $data['plano_entrega_id'] ?? null;
        $ocorrencia->plano_trabalho_id = $data['plano_trabalho_id'] ?? null;
        $ocorrencia->data_inicio = !empty($data['data_inicio']) ? $data['data_inicio'] : now();
        $ocorrencia->data_fim = !empty($data['data_fim']) ? $data['data_fim'] : now();
        $ocorrencia->save();

        return $ocorrencia->load(['usuario:id,nome', 'projeto:id,nome', 'planoEntrega:id,nome']);
    }

    public function destroyProjetoPlano(string $id): void
    {
        $oc = \App\Models\Ocorrencia::find($id);
        if ($oc) {
            $oc->delete();
        }
    }

    public function updateStatusProjetoPlano(string $id, string $status): \App\Models\Ocorrencia
    {
        $oc = \App\Models\Ocorrencia::findOrFail($id);
        $oc->status = $status;
        $oc->save();
        return $oc;
    }
}
