import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom, map, Subject } from 'rxjs';
import { GlobalsService } from './globals.service';
import { MuralAvisoPendente } from '../v2/domain/mural-aviso.types';

export interface AlertaPendenteMural {
  id: string;
  codigo: string;
  origem: string;
  titulo: string;
  descricao: string;
  prazo: string;
  rota: string[];
}

export interface AlertasPendentesResponse {
  habilitado: boolean;
  itens: AlertaPendenteMural[];
}

@Injectable({ providedIn: 'root' })
export class MuralAvisoTenantService {
  private readonly http = inject(HttpClient);
  private readonly gb = inject(GlobalsService);
  private readonly base = '/api/v2/mural-aviso';

  /** Emitido quando alguém solicita a exibição do mural (ex: botão do Topbar). */
  private readonly exibicaoSolicitada = new Subject<void>();
  readonly exibicaoSolicitada$ = this.exibicaoSolicitada.asObservable();

  solicitarExibicao(): void {
    this.exibicaoSolicitada.next();
  }

  getPendentes(incluirLidos = false): Promise<MuralAvisoPendente[]> {
    const params = incluirLidos ? '?incluir_lidos=1' : '';
    return firstValueFrom(
      this.http
        .get<{ success: boolean; data: MuralAvisoPendente[] }>(`${this.gb.servidorURL}${this.base}/pendentes${params}`)
        .pipe(map(response => response?.data || []))
    ).catch(() => []);
  }

  getAlertasPendentes(): Promise<AlertasPendentesResponse> {
    return firstValueFrom(
      this.http
        .get<{ success: boolean; data: AlertasPendentesResponse }>(`${this.gb.servidorURL}${this.base}/alertas-pendentes`)
        .pipe(map(response => response?.data || { habilitado: true, itens: [] }))
    ).catch(() => ({ habilitado: true, itens: [] }));
  }

  confirmarLeitura(): Promise<void> {
    return firstValueFrom(
      this.http
        .post<{ success: boolean }>(`${this.gb.servidorURL}${this.base}/confirmar`, {})
        .pipe(map(() => undefined))
    );
  }

  publicarNoTenant(titulo: string, conteudo: string, dataExpiracao?: string): Promise<any> {
    return firstValueFrom(
      this.http
        .post<{ success: boolean; data: any }>(`${this.gb.servidorURL}${this.base}/publicar`, {
          titulo,
          conteudo,
          data_expiracao: dataExpiracao || null,
        })
        .pipe(map(response => response?.data))
    );
  }

  excluirNoTenant(id: string): Promise<void> {
    return firstValueFrom(
      this.http
        .delete<{ success: boolean }>(`${this.gb.servidorURL}${this.base}/${id}`)
        .pipe(map(() => undefined))
    );
  }
}
