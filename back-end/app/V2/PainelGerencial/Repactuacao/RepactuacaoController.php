<?php

declare(strict_types=1);

namespace App\V2\PainelGerencial\Repactuacao;

use App\Exceptions\Contracts\IBaseException;
use App\Http\Controllers\Controller;
use App\V2\PainelGerencial\PainelGerencialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RepactuacaoController extends Controller
{
    public function __construct(
        private readonly PainelGerencialService $painelService,
        private readonly RepactuacaoDataProvider $dataProvider,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $this->painelService->validarAcesso();

            $ciclo = $request->query('ciclo');
            $status = $request->query('status');
            $unidadeId = $request->query('unidade_id');

            $result = $this->dataProvider->getDadosMonitoramento($ciclo, $status, $unidadeId);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        } catch (IBaseException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode());
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error' => 'Ocorreu um erro ao carregar dados de repactuação.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function entregasPorUnidade(Request $request, string $unidadeId): JsonResponse
    {
        try {
            $this->painelService->validarAcesso();

            $entregas = DB::connection('tenant')->table('planos_entregas_entregas as pee')
                ->join('planos_entregas as pe', 'pe.id', '=', 'pee.plano_entrega_id')
                ->where('pee.unidade_id', $unidadeId)
                ->whereNull('pee.deleted_at')
                ->whereNull('pe.deleted_at')
                ->select([
                    'pee.id',
                    'pee.plano_entrega_id',
                    'pee.descricao_entrega',
                    'pee.meta',
                    'pee.data_inicio',
                    'pee.data_fim',
                    'pee.progresso_realizado',
                    'pe.nome as plano_nome'
                ])
                ->orderBy('pee.descricao_entrega')
                ->get();

            return response()->json(['success' => true, 'data' => $entregas]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error' => 'Erro ao buscar entregas da unidade.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $this->painelService->validarAcesso();

            $validated = $request->validate([
                'plano_entrega_id' => 'nullable|string',
                'plano_entrega_entrega_id' => 'required|string',
                'unidade_id' => 'required|string',
                'ciclo' => 'required|string',
                'tipo' => 'required|string|in:PRAZO,META,ESCOPO,OUTRO',
                'motivo' => 'required|string',
                'descricao_ajuste' => 'required|string',
                'justificativa' => 'required|string',
                'data_nova' => 'nullable|date',
                'meta_nova' => 'nullable|string',
            ]);

            $userId = Auth::id() ?? '011fb24b-29d3-45e8-80dd-05357490b9f2';

            $result = $this->dataProvider->criar($validated, $userId);

            return response()->json($result, Response::HTTP_CREATED);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function homologar(Request $request, string $id): JsonResponse
    {
        try {
            $this->painelService->validarAcesso();

            $parecer = $request->input('parecer');
            $userId = Auth::id() ?? '011fb24b-29d3-45e8-80dd-05357490b9f2';

            $result = $this->dataProvider->homologar($id, $parecer, $userId);

            return response()->json($result);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function rejeitar(Request $request, string $id): JsonResponse
    {
        try {
            $this->painelService->validarAcesso();

            $request->validate(['parecer' => 'required|string']);
            $parecer = $request->input('parecer');
            $userId = Auth::id() ?? '011fb24b-29d3-45e8-80dd-05357490b9f2';

            $result = $this->dataProvider->rejeitar($id, $parecer, $userId);

            return response()->json($result);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
