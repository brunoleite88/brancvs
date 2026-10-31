<?php

namespace App\Models;

use App\Models\ModelBase;
use App\Models\Usuario;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RelatorioCorregedoria extends ModelBase
{
    protected $table = 'relatorios_corregedoria';

    protected $casts = [
        'cabecalho' => 'array',
        'data_assinatura' => 'datetime',
    ];

    public $fillable = [
        'usuario_id',
        'unidade_id',
        'mes',
        'ano',
        'afastamentos_texto',
        'cabecalho',
        'status',
        'assinatura_hash',
        'data_assinatura',
        'observacoes_corregedoria',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'unidade_id');
    }

    public function atos(): HasMany
    {
        return $this->hasMany(RelatorioCorregedoriaAto::class, 'relatorio_corregedoria_id')->orderBy('numero_ordem', 'asc');
    }
}
