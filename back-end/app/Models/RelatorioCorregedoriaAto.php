<?php

namespace App\Models;

use App\Models\ModelBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RelatorioCorregedoriaAto extends ModelBase
{
    protected $table = 'relatorios_corregedoria_atos';

    public $fillable = [
        'relatorio_corregedoria_id',
        'numero_ordem',
        'processo',
        'ato',
        'data_ato',
        'observacao',
    ];

    public function relatorio(): BelongsTo
    {
        return $this->belongsTo(RelatorioCorregedoria::class, 'relatorio_corregedoria_id');
    }
}
