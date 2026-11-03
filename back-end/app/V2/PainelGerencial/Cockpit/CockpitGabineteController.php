<?php

declare(strict_types=1);

namespace App\V2\PainelGerencial\Cockpit;

use App\Exceptions\Contracts\IBaseException;
use App\Http\Controllers\Controller;
use App\V2\PainelGerencial\PainelGerencialService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CockpitGabineteController extends Controller
{
    public function __construct(
        private readonly PainelGerencialService $painelService,
        private readonly CockpitGabineteDataProvider $dataProvider,
    ) {}

    public function index(): JsonResponse
    {
        try {
            $this->painelService->validarAcesso();
            $dados = $this->dataProvider->getDados();

            return response()->json([
                'success' => true,
                'data' => $dados,
            ]);
        } catch (IBaseException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode());
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'Ocorreu um erro ao carregar os dados do Cockpit Executivo do Gabinete.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
