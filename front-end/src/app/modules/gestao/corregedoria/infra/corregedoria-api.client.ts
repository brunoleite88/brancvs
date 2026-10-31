import { Injectable } from '@angular/core';
import { HttpParams } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { TenantV2ResourceApiBase } from 'src/app/v2/infra/tenant-v2-resource-api.base';

export interface RelatorioAto {
  id?: string;
  numero_ordem?: number;
  processo: string;
  ato: string;
  data_ato: string;
  observacao: string;
}

export interface RelatorioCabecalho {
  logo_url?: string;
  ente?: string;
  orgao?: string;
  titulo?: string;
  subtitulo?: string;
  altura_logo?: number;
}

export interface RelatorioCorregedoria {
  id: string;
  usuario_id: string;
  unidade_id: string;
  mes: number;
  ano: number;
  afastamentos_texto: string;
  cabecalho?: RelatorioCabecalho;
  status: 'RASCUNHO' | 'CONCLUIDO_ASSINADO' | 'HOMOLOGADO' | 'RETIFICACAO';
  assinatura_hash?: string;
  data_assinatura?: string;
  observacoes_corregedoria?: string;
  usuario?: { id: string; nome: string; email: string; matricula: string };
  unidade?: { id: string; nome: string; sigla: string };
  atos: RelatorioAto[];
  created_at?: string;
  updated_at?: string;
}

@Injectable({ providedIn: 'root' })
export class CorregedoriaApiClient extends TenantV2ResourceApiBase {
  protected readonly apiPath = '/api/v2/corregedoria';

  getMeuRelatorio(mes: number, ano: number): Observable<RelatorioCorregedoria> {
    return this.http.get<any>(this.resourceUrl(`/meu-relatorio/${mes}/${ano}`))
      .pipe(map(r => r?.data));
  }

  salvarRascunho(payload: any): Observable<RelatorioCorregedoria> {
    return this.http.post<any>(this.resourceUrl('/salvar-rascunho'), payload)
      .pipe(map(r => r?.data));
  }

  assinarRelatorio(id: string): Observable<RelatorioCorregedoria> {
    return this.http.post<any>(this.resourceUrl(`/relatorios/${id}/assinar`), {})
      .pipe(map(r => r?.data));
  }

  listarRelatorios(params: Record<string, any> = {}): Observable<any> {
    const httpParams = new HttpParams({ fromObject: params });
    return this.http.get<any>(this.resourceUrl('/relatorios'), { params: httpParams })
      .pipe(map(r => r?.data));
  }

  getUnidades(): Observable<{ id: string; nome: string; sigla: string }[]> {
    return this.http.get<any>(this.resourceUrl('/unidades'))
      .pipe(map(r => r?.data ?? []));
  }

  analisarRelatorio(id: string, status: string, observacoes?: string): Observable<RelatorioCorregedoria> {
    return this.http.patch<any>(this.resourceUrl(`/relatorios/${id}/analise`), { status, observacoes })
      .pipe(map(r => r?.data));
  }
}
