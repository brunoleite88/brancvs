<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanoEntregaRepactuacao extends ModelBase
{
    protected $table = 'planos_entregas_repactuacoes';

    protected $fillable = [
        'plano_entrega_id',
        'plano_entrega_entrega_id',
        'unidade_id',
        'ciclo',
        'numero_processo_sei',
        'tipo',
        'motivo',
        'descricao_ajuste',
        'justificativa',
        'data_anterior',
        'data_nova',
        'meta_anterior',
        'meta_nova',
        'status',
        'solicitante_id',
        'analisador_id',
        'parecer_analise',
        'data_homologacao',
    ];

    protected $casts = [
        'data_anterior' => 'datetime',
        'data_nova' => 'datetime',
        'data_homologacao' => 'datetime',
    ];

    public function planoEntrega(): BelongsTo
    {
        return $this->belongsTo(PlanoEntrega::class, 'plano_entrega_id');
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(PlanoEntregaEntrega::class, 'plano_entrega_entrega_id');
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'unidade_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'solicitante_id');
    }

    public function analisador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'analisador_id');
    }
}
