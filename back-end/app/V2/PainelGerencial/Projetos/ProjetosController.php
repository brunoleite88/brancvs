<?php

declare(strict_types=1);

namespace App\V2\PainelGerencial\Projetos;

use App\Exceptions\Contracts\IBaseException;
use App\Http\Controllers\Controller;
use App\V2\PainelGerencial\PainelGerencialService;
use App\V2\PainelGerencial\Validators\PainelRequestValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProjetosController extends Controller
{
    public function __construct(
        private readonly PainelGerencialService $painelService,
        private readonly ProjetosDataProvider $projetosDataProvider,
        private readonly PainelRequestValidator $validator,
    ) {}

    public function index(): JsonResponse
    {
        try {
            $this->painelService->validarAcesso();
            $result = $this->projetosDataProvider->getResumo();

            return response()->json(['success' => true, 'data' => $result]);
        } catch (IBaseException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'Ocorreu um erro inesperado.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function indicadorPorUnidade(Request $request): JsonResponse
    {
        try {
            $this->painelService->validarAcesso();
            $filtros = $this->validator->validar($request);
            $result = $this->projetosDataProvider->getIndicadorPorUnidade($filtros);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        } catch (IBaseException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'Ocorreu um erro inesperado.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
