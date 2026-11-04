<?php

namespace App\Services;

use App\Services\ServiceBase;
use App\Models\Usuario;
use App\Models\UnidadeIntegrante;
use App\Models\UnidadeIntegranteAtribuicao;
use App\Exceptions\ServerException;
use App\Services\Siape\Unidade\Enum\Atribuicao;
use Illuminate\Support\Str;

class UnidadeIntegranteAtribuicaoService extends ServiceBase
{

  public function proxyStore($data, $unidade, $action)
  {
    if ($action == ServiceBase::ACTION_INSERT && $data["atribuicao"] == Atribuicao::LOTADO->value) {
      $vinculo = UnidadeIntegrante::find($data["unidade_integrante_id"]);
      $lotacaoAtual = $vinculo?->usuario?->lotacao;

      if (!empty($lotacaoAtual) && $lotacaoAtual->unidade_id != $vinculo->unidade_id) {
        app(UnidadeAtribuicaoService::class)->removerLotacoesAnterioresPreservandoVinculo($vinculo->usuario, $vinculo);
      }
    }
    return $data;
  }

  public function extraStore($entity, $unidade, $action)
  {
    $this->checkLotacoes($entity->vinculo->usuario_id);
  }

  public function extraDestroy($entity)
  {
    $this->checkLotacoes($entity->vinculo->usuario_id);
  }

  public function checkLotacoes($usuarioId)
  {
    $usuario = Usuario::find($usuarioId);
    if (!$usuario) return;

    if (!$usuario->usuario_externo && !isset($usuario->lotacao)) {
      // Se o usuário possui vínculo ativo com alguma unidade mas não possui a atribuição 'LOTADO',
      // promove automaticamente o vínculo existente para LOTADO para evitar inconsistência de lotação.
      $vinculo = UnidadeIntegrante::where('usuario_id', $usuarioId)->whereNull('deleted_at')->first();
      if ($vinculo) {
        $temLotado = UnidadeIntegranteAtribuicao::where('unidade_integrante_id', $vinculo->id)
          ->where('atribuicao', 'LOTADO')
          ->whereNull('deleted_at')
          ->exists();
        if (!$temLotado) {
          UnidadeIntegranteAtribuicao::create([
            'id' => (string) Str::uuid(),
            'unidade_integrante_id' => $vinculo->id,
            'atribuicao' => 'LOTADO'
          ]);
          return;
        }
      } else {
        throw new ServerException("ValidateLotacao", "Usuário não pode ficar sem lotação. Por favor, adicione uma unidade na aba Atribuições.");
      }
    }
  }
}
