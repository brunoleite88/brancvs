<?php

namespace App\Services;

use App\Models\EixoTematico;
use App\Services\ServiceBase;

class EixoTematicoService extends ServiceBase {
  public function proxyQuery(&$query, &$data) {
    $wherePlan = $this->extractWhere($data, "planejamento_id");
    if (!empty($wherePlan) && !empty($wherePlan[2])) {
      $planejamentoId = $wherePlan[2];
      $query->whereIn('eixos_tematicos.id', function($sub) use ($planejamentoId) {
        $sub->select('eixo_tematico_id')
            ->from('planejamentos_objetivos')
            ->where('planejamento_id', $planejamentoId)
            ->whereNotNull('eixo_tematico_id');
      });
    }
  }
}
