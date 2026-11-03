import { Injectable } from '@angular/core';
import { Observable, map } from 'rxjs';
import { TenantV2ResourceApiBase } from 'src/app/v2/infra/tenant-v2-resource-api.base';

export interface DistribuicaoUnidade {
  unidade_id: string;
  unidade_sigla: string;
  valores: number[];
  total: number;
}

export interface DrillTarget {
  unidade_id: string;
  unidade_sigla: string;
}

export interface Indicador {
  segmentos: string[];
  distribuicoes: DistribuicaoUnidade[];
}

export interface IndicadorTeletrabalho {
  taxa: number;
  limite: number;
  participantes_modalidade: number;
  total_participantes: number;
}

export interface UnidadeInicial {
  unidade_id: string | null;
  unidade_sigla: string | null;
  unidade_nome: string | null;
  unidade_raiz_id: string | null;
  unidade_raiz_sigla: string | null;
}

export interface FiltrosPainel {
  tipo_consulta: 'situacao_atual' | 'historico';
  unidade_id: string;
  data_inicio?: string;
  data_fim?: string;
}

export interface SerieAdesaoItem {
  periodo: string;
  executoras?: number;
  nao_executoras?: number;
  participantes?: number;
  nao_participantes?: number;
}

export interface SerieAdesao {
  serie: SerieAdesaoItem[];
}

export interface ProjetoMarcoItem {
  id: string;
  nome: string;
  descricao: string;
  data_inicio: string | null;
  data_fim: string | null;
  progresso: number;
  status: string;
}

export interface ProjetoResumoItem {
  id: string;
  codigo: string;
  titulo: string;
  tipo: string;
  lider: string;
  unidades_participantes: string;
  status: string;
  prazo: string;
  objetivo: string;
  krs_vinculados: string;
  progresso: number;
  numero_processo_sei?: string;
  fonte_financiamento?: string;
  risco_principal?: string;
  marcos?: ProjetoMarcoItem[];
}

export interface ProjetosPainelResponse {
  totais: {
    total: number;
    em_execucao: number;
    em_implantacao: number;
    em_elaboracao: number;
    media_progresso: number;
    total_marcos?: number;
    marcos_concluidos?: number;
  };
  projetos: ProjetoResumoItem[];
}

export interface CockpitKpis {
  total_unidades: number;
  total_planos_ativos: number;
  cobertura_percentual: number;
  total_entregas: number;
  projetos_gt_total: number;
  farol: {
    verde: number;
    amarelo: number;
    vermelho: number;
  };
  faixas_prazo: {
    curto_prazo: number;
    medio_prazo: number;
    estruturante: number;
  };
  pilares: Record<string, number>;
}

export interface CockpitMacroArea {
  macro_area: string;
  total_unidades: number;
  total_entregas: number;
  verde: number;
  amarelo: number;
  vermelho: number;
  progresso_medio: number;
}

export interface CockpitUnidadeDetalhe {
  id: string;
  sigla: string;
  nome: string;
  macro_area: string;
  plano_id: string | null;
  plano_numero: number | null;
  plano_nome: string | null;
  total_entregas: number;
  progresso_medio: number;
  farol: 'VERDE' | 'AMARELO' | 'VERMELHO';
  situacao: string;
  pilar_predominante: string;
}

export interface CockpitGabineteResponse {
  kpis: CockpitKpis;
  macro_areas: CockpitMacroArea[];
  unidades: CockpitUnidadeDetalhe[];
}

export interface RepactuacaoItem {
  id: string;
  plano_entrega_id: string;
  plano_entrega_entrega_id: string | null;
  unidade_id: string;
  unidade_sigla: string;
  unidade_nome: string;
  ciclo: string;
  numero_processo_sei?: string;
  tipo: 'PRAZO' | 'META' | 'ESCOPO' | 'OUTRO';
  motivo: string;
  descricao_ajuste: string;
  justificativa: string;
  data_anterior: string | null;
  data_nova: string | null;
  meta_anterior: string | null;
  meta_nova: string | null;
  status: 'PENDENTE' | 'HOMOLOGADO' | 'REJEITADO';
  solicitante_id: string;
  solicitante_nome: string;
  analisador_id: string | null;
  analisador_nome: string | null;
  parecer_analise: string | null;
  data_homologacao: string | null;
  created_at: string;
  descricao_entrega?: string;
  meta_atual_entrega?: string;
  progresso_atual_entrega?: number;
}

export interface RepactuacaoMotivoSummary {
  motivo: string;
  label: string;
  total: number;
  percentual: number;
}

export interface RepactuacaoCicloSummary {
  ciclo: string;
  total: number;
  pendentes: number;
  homologadas: number;
}

export interface RepactuacaoTotais {
  total_solicitacoes: number;
  pendentes: number;
  homologadas: number;
  rejeitadas: number;
  unidades_impactadas: number;
  taxa_homologacao: number;
}

export interface RepactuacoesResponse {
  totais: RepactuacaoTotais;
  por_motivo: RepactuacaoMotivoSummary[];
  por_ciclo: RepactuacaoCicloSummary[];
  unidades: Array<{ id: string; sigla: string; nome: string }>;
  repactuacoes: RepactuacaoItem[];
}

export interface UnidadeHistorica {
  id: string;
  sigla: string;
  nome: string;
}

@Injectable()
export class PainelApiClient extends TenantV2ResourceApiBase {
  protected readonly apiPath = '/api/v2/painel-gerencial';

  private filtrosToParams(filtros: FiltrosPainel): Record<string, string> {
    const params: Record<string, string> = {
      tipo_consulta: filtros.tipo_consulta,
      unidade_id: filtros.unidade_id,
    };
    if (filtros.data_inicio) params['data_inicio'] = filtros.data_inicio;
    if (filtros.data_fim) params['data_fim'] = filtros.data_fim;
    return params;
  }

  getUnidadeInicial(): Observable<UnidadeInicial> {
    return this.http
      .get<{ data: UnidadeInicial }>(this.resourceUrl('/unidade-inicial'))
      .pipe(map(r => r.data));
  }

  getAlinhamentoInstitucional(filtros: FiltrosPainel): Observable<Indicador> {
    return this.http
      .get<{ data: Indicador }>(this.resourceUrl('/alinhamento-desempenho/alinhamento-institucional'), {
        params: this.filtrosToParams(filtros),
      })
      .pipe(map(r => r.data));
  }

  getAvaliacoesPlanoEntrega(filtros: FiltrosPainel): Observable<Indicador> {
    return this.http
      .get<{ data: Indicador }>(this.resourceUrl('/alinhamento-desempenho/avaliacoes-plano-entrega'), {
        params: this.filtrosToParams(filtros),
      })
      .pipe(map(r => r.data));
  }

  getAvaliacoesPlanoTrabalho(filtros: FiltrosPainel): Observable<Indicador> {
    return this.http
      .get<{ data: Indicador }>(this.resourceUrl('/alinhamento-desempenho/avaliacoes-plano-trabalho'), {
        params: this.filtrosToParams(filtros),
      })
      .pipe(map(r => r.data));
  }

  getTeletrabalhoSubstituicao(filtros: FiltrosPainel): Observable<IndicadorTeletrabalho> {
    return this.http
      .get<{ data: IndicadorTeletrabalho }>(this.resourceUrl('/modalidades/teletrabalho-substituicao'), {
        params: this.filtrosToParams(filtros),
      })
      .pipe(map(r => r.data));
  }

  getTeletrabalhoDiscricionario(filtros: FiltrosPainel): Observable<IndicadorTeletrabalho> {
    return this.http
      .get<{ data: IndicadorTeletrabalho }>(this.resourceUrl('/modalidades/teletrabalho-discricionario'), {
        params: this.filtrosToParams(filtros),
      })
      .pipe(map(r => r.data));
  }

  getModalidadesPorUnidade(filtros: FiltrosPainel): Observable<Indicador> {
    return this.http
      .get<{ data: Indicador }>(this.resourceUrl('/modalidades/por-unidade'), {
        params: this.filtrosToParams(filtros),
      })
      .pipe(map(r => r.data));
  }

  getProjetosPainel(): Observable<ProjetosPainelResponse> {
    return this.http
      .get<{ data: ProjetosPainelResponse }>(this.resourceUrl('/projetos'))
      .pipe(map(r => r.data));
  }

  getCockpitGabinete(): Observable<CockpitGabineteResponse> {
    return this.http
      .get<{ data: CockpitGabineteResponse }>(this.resourceUrl('/cockpit-gabinete'))
      .pipe(map(r => r.data));
  }

  getProjetosIndicador(filtros: FiltrosPainel): Observable<Indicador> {
    return this.http
      .get<{ data: Indicador }>(this.resourceUrl('/projetos/indicador'), {
        params: this.filtrosToParams(filtros),
      })
      .pipe(map(r => r.data));
  }

  getConformidadeAvaliacaoPE(filtros: FiltrosPainel): Observable<Indicador> {
    return this.http
      .get<{ data: Indicador }>(this.resourceUrl('/conformidade/avaliacao-pe'), {
        params: this.filtrosToParams(filtros),
      })
      .pipe(map(r => r.data));
  }

  getConformidadeRegistroExecucaoPE(filtros: FiltrosPainel): Observable<Indicador> {
    return this.http
      .get<{ data: Indicador }>(this.resourceUrl('/conformidade/registro-execucao-pe'), {
        params: this.filtrosToParams(filtros),
      })
      .pipe(map(r => r.data));
  }

  getConformidadeRegistroExecucaoPT(filtros: FiltrosPainel): Observable<Indicador> {
    return this.http
      .get<{ data: Indicador }>(this.resourceUrl('/conformidade/registro-execucao-pt'), {
        params: this.filtrosToParams(filtros),
      })
      .pipe(map(r => r.data));
  }

  getConformidadeAvaliacaoPT(filtros: FiltrosPainel): Observable<Indicador> {
    return this.http
      .get<{ data: Indicador }>(this.resourceUrl('/conformidade/avaliacao-pt'), {
        params: this.filtrosToParams(filtros),
      })
      .pipe(map(r => r.data));
  }

  getConformidadeUnidadesExecutorasPE(filtros: FiltrosPainel): Observable<Indicador> {
    return this.http
      .get<{ data: Indicador }>(this.resourceUrl('/conformidade/unidades-executoras-pe'), {
        params: this.filtrosToParams(filtros),
      })
      .pipe(map(r => r.data));
  }

  // Adesão

  getUnidadesExecutoras(filtros: FiltrosPainel): Observable<Indicador> {
    return this.http
      .get<{ data: Indicador }>(this.resourceUrl('/adesao/unidades-executoras'), {
        params: this.filtrosToParams(filtros),
      })
      .pipe(map(r => r.data));
  }

  getEvolucaoUnidades(filtros: FiltrosPainel): Observable<SerieAdesao> {
    return this.http
      .get<{ data: SerieAdesao }>(this.resourceUrl('/adesao/evolucao-unidades'), {
        params: this.filtrosToParams(filtros),
      })
      .pipe(map(r => r.data));
  }

  getParticipantesPGD(filtros: FiltrosPainel): Observable<Indicador> {
    return this.http
      .get<{ data: Indicador }>(this.resourceUrl('/adesao/participantes-pgd'), {
        params: this.filtrosToParams(filtros),
      })
      .pipe(map(r => r.data));
  }

  getEvolucaoParticipantes(filtros: FiltrosPainel): Observable<SerieAdesao> {
    return this.http
      .get<{ data: SerieAdesao }>(this.resourceUrl('/adesao/evolucao-participantes'), {
        params: this.filtrosToParams(filtros),
      })
      .pipe(map(r => r.data));
  }

  getPeriodosDisponiveis(): Observable<string[]> {
    return this.http
      .get<{ data: string[] }>(this.resourceUrl('/adesao/periodos-disponiveis'))
      .pipe(map(r => r.data));
  }

  getPeriodosDisponiveisPorUnidade(unidadeId: string): Observable<string[]> {
    return this.http
      .get<{ data: string[] }>(this.resourceUrl('/adesao/periodos-disponiveis-por-unidade'), {
        params: { unidade_id: unidadeId },
      })
      .pipe(map(r => r.data));
  }

  getUnidadesHistoricas(): Observable<UnidadeHistorica[]> {
    return this.http
      .get<{ data: UnidadeHistorica[] }>(this.resourceUrl('/adesao/unidades-historicas'))
      .pipe(map(r => r.data));
  }

  getRepactuacoes(ciclo?: string, status?: string, unidadeId?: string): Observable<RepactuacoesResponse> {
    const params: Record<string, string> = {};
    if (ciclo) params['ciclo'] = ciclo;
    if (status) params['status'] = status;
    if (unidadeId) params['unidade_id'] = unidadeId;

    return this.http
      .get<{ data: RepactuacoesResponse }>(this.resourceUrl('/repactuacoes'), { params })
      .pipe(map(r => r.data));
  }

  getEntregasPorUnidade(unidadeId: string): Observable<any[]> {
    return this.http
      .get<{ data: any[] }>(this.resourceUrl(`/repactuacoes/entregas-unidade/${unidadeId}`))
      .pipe(map(r => r.data));
  }

  criarRepactuacao(payload: any): Observable<any> {
    return this.http.post(this.resourceUrl('/repactuacoes'), payload);
  }

  homologarRepactuacao(id: string, parecer?: string): Observable<any> {
    return this.http.post(this.resourceUrl(`/repactuacoes/${id}/homologar`), { parecer });
  }

  rejeitarRepactuacao(id: string, parecer: string): Observable<any> {
    return this.http.post(this.resourceUrl(`/repactuacoes/${id}/rejeitar`), { parecer });
  }

  getTermoSeiUrl(id: string): string {
    return this.resourceUrl(`/repactuacoes/${id}/termo-sei`);
  }

  downloadTermoSei(id: string): Observable<Blob> {
    return this.http.get(this.resourceUrl(`/repactuacoes/${id}/termo-sei`), {
      responseType: 'blob',
    });
  }

  vincularSei(id: string, numeroProcessoSei: string): Observable<any> {
    return this.http.post(this.resourceUrl(`/repactuacoes/${id}/vincular-sei`), {
      numero_processo_sei: numeroProcessoSei,
    });
  }
}
