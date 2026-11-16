<?php

declare(strict_types=1);

namespace App\V2\PainelGerencial\Cockpit;

use App\Exceptions\Contracts\IBaseException;
use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\V2\PainelGerencial\PainelGerencialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CockpitGabineteController extends Controller
{
    public function __construct(
        private readonly PainelGerencialService $painelService,
        private readonly CockpitGabineteDataProvider $dataProvider,
        private readonly CockpitConfigService $configService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $usuario = $this->autorizarLeitura();
            if ($usuario instanceof JsonResponse) {
                return $usuario;
            }

            $filtros = $request->only(['status_planos', 'data_inicio', 'data_fim']);
            $dados = $this->dataProvider->getDados($filtros);
            $dados['pode_editar'] = $this->podeConfigurar($usuario);

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

    public function unidadeDetalhe(string $unidadeId): JsonResponse
    {
        try {
            $usuario = $this->autorizarLeitura();
            if ($usuario instanceof JsonResponse) {
                return $usuario;
            }

            $detalhe = $this->dataProvider->getUnidadeDetalhe($unidadeId);
            if (empty($detalhe)) {
                return response()->json(['error' => 'Unidade não encontrada.'], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data' => $detalhe,
            ]);
        } catch (IBaseException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode());
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'error' => 'Ocorreu um erro ao carregar o detalhamento da unidade.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getConfig(): JsonResponse
    {
        try {
            $usuario = $this->autorizarLeitura();
            if ($usuario instanceof JsonResponse) {
                return $usuario;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'config' => $this->configService->getConfig(),
                    'pode_editar' => $this->podeConfigurar($usuario),
                ],
            ]);
        } catch (IBaseException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode());
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'error' => 'Erro ao obter configuração do Cockpit.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateConfig(Request $request): JsonResponse
    {
        try {
            $usuario = $this->autorizarLeitura();
            if ($usuario instanceof JsonResponse) {
                return $usuario;
            }

            if (!$this->podeConfigurar($usuario)) {
                return response()->json([
                    'error' => 'Permissão insuficiente para configurar o Cockpit do Gabinete (MOD_PAINEL_COCKPIT_CFG).',
                ], Response::HTTP_FORBIDDEN);
            }

            $validated = $request->validate([
                'titulo' => 'nullable|string|max:180',
                'subtitulo' => 'nullable|string|max:300',
                'widgets' => 'nullable|array',
                'macro_areas' => 'nullable|array',
                'regras_farol' => 'nullable|array',
                'opcoes' => 'nullable|array',
            ]);

            $config = $this->configService->saveConfig($validated, $usuario?->id);

            return response()->json([
                'success' => true,
                'data' => $config,
            ]);
        } catch (IBaseException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode());
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'error' => 'Erro ao salvar configuração do Cockpit.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function resetConfig(): JsonResponse
    {
        try {
            $usuario = $this->autorizarLeitura();
            if ($usuario instanceof JsonResponse) {
                return $usuario;
            }

            if (!$this->podeConfigurar($usuario)) {
                return response()->json([
                    'error' => 'Permissão insuficiente para restaurar a configuração do Cockpit.',
                ], Response::HTTP_FORBIDDEN);
            }

            $config = $this->configService->resetConfig($usuario?->id);

            return response()->json([
                'success' => true,
                'data' => $config,
            ]);
        } catch (IBaseException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode());
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'error' => 'Erro ao restaurar configuração padrão do Cockpit.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function autorizarLeitura(): Usuario|JsonResponse|null
    {
        $this->painelService->validarAcesso();

        /** @var Usuario|null $usuario */
        $usuario = Auth::user();
        if ($usuario && !$usuario->hasPermissionTo('MOD_PAINEL_COCKPIT') && !$usuario->is_admin) {
            return response()->json([
                'error' => 'Permissão insuficiente para acessar o Cockpit do Gabinete (MOD_PAINEL_COCKPIT).',
            ], Response::HTTP_FORBIDDEN);
        }

        return $usuario;
    }

    private function podeConfigurar(?Usuario $usuario): bool
    {
        if (!$usuario) {
            return false;
        }
        return (bool) $usuario->is_admin
            || $usuario->hasPermissionTo('MOD_PAINEL_COCKPIT_CFG')
            || ($usuario->hasPermissionTo('MOD_PAINEL_COCKPIT') && $usuario->hasPermissionTo('MOD_CFG'));
    }
}
