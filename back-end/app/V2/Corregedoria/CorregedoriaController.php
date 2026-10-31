<?php

declare(strict_types=1);

namespace App\V2\Corregedoria;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CorregedoriaController extends Controller
{
    public function __construct(
        private readonly CorregedoriaService $service,
    ) {}

    public function meuRelatorio(Request $request, int $mes, int $ano): JsonResponse
    {
        try {
            $usuarioId = Auth::id() ?? $request->input('usuario_id');
            $unidadeId = $request->input('unidade_id');
            $relatorio = $this->service->buscarOuCriarMeuRelatorio($usuarioId, $mes, $ano, $unidadeId);

            return response()->json(['success' => true, 'data' => $relatorio]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function salvarRascunho(Request $request): JsonResponse
    {
        try {
            $usuarioId = Auth::id() ?? $request->input('usuario_id');
            $relatorio = $this->service->salvarRascunho($usuarioId, $request->all());

            return response()->json(['success' => true, 'data' => $relatorio]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function assinar(Request $request, string $id): JsonResponse
    {
        try {
            $usuarioId = Auth::id() ?? $request->input('usuario_id');
            $relatorio = $this->service->assinarDigitalmente($usuarioId, $id);

            return response()->json(['success' => true, 'data' => $relatorio]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function unidades(): JsonResponse
    {
        try {
            $result = $this->service->listarUnidades();

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function opcoesVinculo(Request $request): JsonResponse
    {
        try {
            $unidade = $request->input('unidade') ?? $request->input('unidade_id');
            $result = $this->service->obterOpcoesVinculo($unidade);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $result = $this->service->listarRelatorios($request->all());

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function analiseCorregedoria(Request $request, string $id): JsonResponse
    {
        try {
            $corregedorId = Auth::id();
            $status = $request->input('status', 'HOMOLOGADO');
            $observacoes = $request->input('observacoes');
            $relatorio = $this->service->analiseCorregedoria($corregedorId, $id, $status, $observacoes);

            return response()->json(['success' => true, 'data' => $relatorio]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
