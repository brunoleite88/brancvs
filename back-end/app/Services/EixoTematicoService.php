<?php

namespace App\Services;

use App\Services\ServiceBase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EixoTematicoService extends ServiceBase {
  private ?string $planejamentoFiltroId = null;

  /**
   * Aplica filtro por planejamento institucional na consulta de eixos temáticos.
   */
  public function proxyQuery(&$query, &$data) {
    $this->planejamentoFiltroId = null;
    $wherePlan = $this->extractWhere($data, "planejamento_id");
    if (!empty($wherePlan) && !empty($wherePlan[2])) {
      $planejamentoId = (string) $wherePlan[2];
      $this->planejamentoFiltroId = $planejamentoId;
      $query->whereIn('eixos_tematicos.id', function($sub) use ($planejamentoId) {
        $sub->select('eixo_tematico_id')
            ->from('planejamentos_objetivos')
            ->where('planejamento_id', $planejamentoId)
            ->whereNotNull('eixo_tematico_id')
            ->whereNull('deleted_at');
      });
    }
  }

  /**
   * Enriquece os eixos com planejamentos, objetivos, KRs, entregas e unidades vinculadas.
   */
  public function proxyRows($rows) {
    $planejamentoId = $this->planejamentoFiltroId;
    $this->planejamentoFiltroId = null;

    if ($rows->isEmpty()) {
      return $rows;
    }

    $eixoIds = $rows->pluck('id')->filter()->values()->all();
    if (empty($eixoIds)) {
      return $rows;
    }

    $objetivos = $this->carregarObjetivosPorEixos($eixoIds, $planejamentoId);
    if ($objetivos->isEmpty()) {
      foreach ($rows as $row) {
        $this->preencherResumoVazio($row);
      }
      return $rows;
    }

    $objetivoIds = $objetivos->pluck('id')->filter()->values()->all();
    $indiceEntregas = $this->carregarIndiceEntregas($objetivoIds);
    $objetivosPorEixo = $objetivos->groupBy('eixo_tematico_id');

    foreach ($rows as $row) {
      $itensEixo = $objetivosPorEixo->get($row->id, collect());
      if ($itensEixo->isEmpty()) {
        $this->preencherResumoVazio($row);
        continue;
      }
      $this->preencherResumoEixo($row, $itensEixo, $indiceEntregas);
    }

    return $rows;
  }

  private function carregarObjetivosPorEixos(array $eixoIds, ?string $planejamentoId): Collection {
    return DB::table('planejamentos_objetivos as po')
      ->join('planejamentos as p', 'p.id', '=', 'po.planejamento_id')
      ->leftJoin('planejamentos_tipos_objetivos as pto', 'pto.id', '=', 'po.tipo_objetivo_id')
      ->whereIn('po.eixo_tematico_id', $eixoIds)
      ->whereNull('po.deleted_at')
      ->whereNull('p.deleted_at')
      ->when($planejamentoId, fn($q) => $q->where('po.planejamento_id', $planejamentoId))
      ->select([
        'po.id',
        'po.eixo_tematico_id',
        'po.planejamento_id',
        'po.objetivo_pai_id',
        'po.integra_okr',
        'po.sequencia',
        'po.nome',
        'p.nome as planejamento_nome',
        'pto.nome as tipo_objetivo_nome',
      ])
      ->orderBy('po.sequencia')
      ->orderBy('po.nome')
      ->get();
  }

  /**
   * Agrupa entregas e unidades únicas por objetivo_id e por eixo_tematico_id em uma única consulta.
   */
  private function carregarIndiceEntregas(array $objetivoIds): array {
    $registros = DB::table('planos_entregas_entregas_objetivos as peeo')
      ->join('planos_entregas_entregas as pee', 'pee.id', '=', 'peeo.entrega_id')
      ->join('planos_entregas as pe', 'pe.id', '=', 'pee.plano_entrega_id')
      ->join('planejamentos_objetivos as po', 'po.id', '=', 'peeo.planejamento_objetivo_id')
      ->whereIn('peeo.planejamento_objetivo_id', $objetivoIds)
      ->whereNull('peeo.deleted_at')
      ->whereNull('pee.deleted_at')
      ->whereNull('pe.deleted_at')
      ->whereNull('po.deleted_at')
      ->select([
        'po.eixo_tematico_id',
        'peeo.planejamento_objetivo_id',
        'pee.id as entrega_id',
        'pe.unidade_id',
      ])
      ->get();

    $porObjetivo = [];
    $porEixo = [];

    foreach ($registros as $item) {
      $objId = $item->planejamento_objetivo_id;
      $eixoId = $item->eixo_tematico_id;

      $porObjetivo[$objId]['entregas'][$item->entrega_id] = true;
      if (!empty($item->unidade_id)) {
        $porObjetivo[$objId]['unidades'][$item->unidade_id] = true;
      }

      $porEixo[$eixoId]['entregas'][$item->entrega_id] = true;
      if (!empty($item->unidade_id)) {
        $porEixo[$eixoId]['unidades'][$item->unidade_id] = true;
      }
    }

    return [
      'por_objetivo' => $porObjetivo,
      'por_eixo' => $porEixo,
    ];
  }

  private function preencherResumoVazio($row): void {
    $row->planejamentos = [];
    $row->qtd_objetivos = 0;
    $row->qtd_krs = 0;
    $row->qtd_entregas = 0;
    $row->qtd_unidades = 0;
    $row->objetivos_detalhes = [];
  }

  private function preencherResumoEixo($row, Collection $itensEixo, array $indiceEntregas): void {
    $porObjetivo = $indiceEntregas['por_objetivo'];
    $setsEixo = $indiceEntregas['por_eixo'][$row->id] ?? ['entregas' => [], 'unidades' => []];

    $planejamentos = [];
    $objetivosRaiz = [];
    $krsPorPai = [];
    $qtdObjetivos = 0;
    $qtdKrs = 0;

    foreach ($itensEixo as $item) {
      $planejamentos[$item->planejamento_id] = [
        'id' => $item->planejamento_id,
        'nome' => $item->planejamento_nome,
      ];

      if ($this->ehObjetivoEstrategico($item)) {
        $qtdObjetivos++;
        $objetivosRaiz[$item->id] = $item;
        continue;
      }

      $qtdKrs++;
      $paiKey = (!empty($item->objetivo_pai_id)) ? $item->objetivo_pai_id : '__sem_objetivo__';
      $krsPorPai[$paiKey][] = $item;
    }

    $detalhes = [];
    foreach ($objetivosRaiz as $objId => $obj) {
      $filhos = $krsPorPai[$objId] ?? [];
      unset($krsPorPai[$objId]);
      $detalhes[] = $this->montarBlocoObjetivo($objId, $obj->nome, $obj->planejamento_nome, $filhos, $porObjetivo);
    }

    // Preserva KRs órfãos caso existam fora da hierarquia padrão objetivo_pai_id
    foreach ($krsPorPai as $paiId => $krsRestantes) {
      $primeiro = $krsRestantes[0];
      $detalhes[] = $this->montarBlocoObjetivo(
        $paiId,
        'Resultados-chave vinculados diretamente ao eixo',
        $primeiro->planejamento_nome,
        $krsRestantes,
        $porObjetivo,
        false
      );
    }

    $row->planejamentos = array_values($planejamentos);
    $row->qtd_objetivos = $qtdObjetivos;
    $row->qtd_krs = $qtdKrs;
    $row->qtd_entregas = count($setsEixo['entregas'] ?? []);
    $row->qtd_unidades = count($setsEixo['unidades'] ?? []);
    $row->objetivos_detalhes = $detalhes;
  }

  private function montarBlocoObjetivo(
    string $objId,
    string $nome,
    string $planejamentoNome,
    array $krs,
    array $porObjetivo,
    bool $incluirEntregasProprias = true
  ): array {
    $entregasSet = $incluirEntregasProprias ? ($porObjetivo[$objId]['entregas'] ?? []) : [];
    $unidadesSet = $incluirEntregasProprias ? ($porObjetivo[$objId]['unidades'] ?? []) : [];
    $krsFormatados = [];

    foreach ($krs as $kr) {
      $krEntregas = $porObjetivo[$kr->id]['entregas'] ?? [];
      $krUnidades = $porObjetivo[$kr->id]['unidades'] ?? [];
      $entregasSet += $krEntregas;
      $unidadesSet += $krUnidades;

      $krsFormatados[] = [
        'id' => $kr->id,
        'nome' => $kr->nome,
        'qtd_entregas' => count($krEntregas),
        'qtd_unidades' => count($krUnidades),
      ];
    }

    return [
      'id' => $objId,
      'nome' => $nome,
      'planejamento_nome' => $planejamentoNome,
      'qtd_krs' => count($krsFormatados),
      'qtd_entregas' => count($entregasSet),
      'qtd_unidades' => count($unidadesSet),
      'krs' => $krsFormatados,
    ];
  }

  /**
   * Considera objetivo estratégico quando o tipo não for explicitamente KR/Meta e for raiz ou integra_okr=0.
   */
  private function ehObjetivoEstrategico(object $item): bool {
    $tipoNome = mb_strtolower((string) ($item->tipo_objetivo_nome ?? ''), 'UTF-8');
    if (str_contains($tipoNome, '(kr)') || str_contains($tipoNome, 'meta')) {
      return false;
    }
    return (int) $item->integra_okr === 0 || empty($item->objetivo_pai_id);
  }
}
