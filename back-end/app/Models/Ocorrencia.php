<?php

namespace App\Models;

use App\Models\ModelBase;
use App\Models\Usuario;
use App\Models\PlanoTrabalho;
use App\Models\PlanoTrabalhoConsolidacaoOcorrencia;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ocorrencia extends ModelBase
{
  protected $table = 'ocorrencias';

  protected $with = [];

  public $fillable = [
    'data_inicio',
    'data_fim',
    'descricao',
    'usuario_id',
    'plano_trabalho_id',
    'tipo',
    'titulo',
    'motivo',
    'impacto',
    'novo_prazo_proposto',
    'acao_mitigatoria',
    'status',
    'projeto_id',
    'projeto_tarefa_id',
    'plano_entrega_id',
    'plano_entrega_entrega_id',
  ];

  public $fillable_changes = [];

  public $delete_cascade = [];

  // Has
  public function consolidacoes(): HasMany
  {
    return $this->hasMany(PlanoTrabalhoConsolidacaoOcorrencia::class);
  }
  // Belongs
  public function usuario(): BelongsTo
  {
    return $this->belongsTo(Usuario::class);
  }
  public function planoTrabalho(): BelongsTo
  {
    return $this->belongsTo(PlanoTrabalho::class);
  }
  public function projeto(): BelongsTo
  {
    return $this->belongsTo(Projeto::class);
  }
  public function planoEntrega(): BelongsTo
  {
    return $this->belongsTo(PlanoEntrega::class);
  }
}
