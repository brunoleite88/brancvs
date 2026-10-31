import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CorregedoriaApiClient, RelatorioCorregedoria } from '../infra/corregedoria-api.client';
import { MessageService } from 'src/app/v2/services/message.service';
import { BreadcrumbComponent } from 'src/app/v2/components/breadcrumb/breadcrumb.component';
import { BreadcrumbService } from 'src/app/v2/components/breadcrumb/breadcrumb.service';

@Component({
  selector: 'app-corregedoria-painel',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [CommonModule, FormsModule, BreadcrumbComponent],
  providers: [BreadcrumbService, CorregedoriaApiClient],
  templateUrl: './corregedoria-painel.component.html',
  styleUrls: ['./corregedoria-painel.component.scss'],
})
export class CorregedoriaPainelComponent implements OnInit {
  private readonly api = inject(CorregedoriaApiClient);
  private readonly message = inject(MessageService);
  private readonly cd = inject(ChangeDetectorRef);

  readonly relatorios = signal<RelatorioCorregedoria[]>([]);
  readonly unidades = signal<{ id: string; nome: string; sigla: string }[]>([]);
  readonly carregando = signal(true);
  readonly relatorioSelecionado = signal<RelatorioCorregedoria | null>(null);
  readonly modalVisualizacaoAberto = signal(false);

  readonly filtroMes = signal<number>(10);
  readonly filtroAno = signal<number>(2026);
  readonly filtroStatus = signal<string>('TODOS');
  readonly filtroUnidadeId = signal<string>('');

  readonly meses = [
    { valor: 0, nome: 'Todos os Meses' },
    { valor: 1, nome: 'Janeiro' },
    { valor: 2, nome: 'Fevereiro' },
    { valor: 3, nome: 'Março' },
    { valor: 4, nome: 'Abril' },
    { valor: 5, nome: 'Maio' },
    { valor: 6, nome: 'Junho' },
    { valor: 7, nome: 'Julho' },
    { valor: 8, nome: 'Agosto' },
    { valor: 9, nome: 'Setembro' },
    { valor: 10, nome: 'Outubro' },
    { valor: 11, nome: 'Novembro' },
    { valor: 12, nome: 'Dezembro' },
  ];

  readonly anos = [2025, 2026, 2027];

  ngOnInit(): void {
    const hoje = new Date();
    this.filtroMes.set(hoje.getMonth() + 1);
    this.filtroAno.set(hoje.getFullYear() < 2026 ? 2026 : hoje.getFullYear());
    this.carregarUnidades();
    this.carregarRelatorios();
  }

  carregarUnidades(): void {
    this.api.getUnidades().subscribe({
      next: (list) => {
        this.unidades.set(list);
        this.cd.markForCheck();
      }
    });
  }

  carregarRelatorios(): void {
    this.carregando.set(true);
    const params: Record<string, any> = {
      ano: this.filtroAno(),
      size: 50,
    };
    if (this.filtroMes() > 0) {
      params['mes'] = this.filtroMes();
    }
    if (this.filtroStatus() !== 'TODOS') {
      params['status'] = this.filtroStatus();
    }
    if (this.filtroUnidadeId()) {
      params['unidade_id'] = this.filtroUnidadeId();
    }

    this.api.listarRelatorios(params).subscribe({
      next: (res) => {
        this.relatorios.set(res?.data ?? []);
        this.carregando.set(false);
        this.cd.markForCheck();
      },
      error: () => {
        this.carregando.set(false);
        this.cd.markForCheck();
        this.message.error('Erro ao carregar relatórios da Corregedoria.');
      }
    });
  }

  visualizar(rel: RelatorioCorregedoria): void {
    this.relatorioSelecionado.set(rel);
    this.modalVisualizacaoAberto.set(true);
  }

  fecharModal(): void {
    this.modalVisualizacaoAberto.set(false);
    this.relatorioSelecionado.set(null);
  }

  homologar(rel: RelatorioCorregedoria): void {
    if (!confirm(`Deseja homologar o relatório de ${rel.usuario?.nome}?`)) return;

    this.api.analisarRelatorio(rel.id, 'HOMOLOGADO').subscribe({
      next: (atualizado) => {
        this.message.success('Relatório homologado com sucesso!');
        this.fecharModal();
        this.carregarRelatorios();
      },
      error: () => this.message.error('Erro ao homologar relatório.')
    });
  }

  solicitarRetificacao(rel: RelatorioCorregedoria): void {
    const motivo = prompt('Informe a justificativa ou orientação para retificação:');
    if (!motivo) return;

    this.api.analisarRelatorio(rel.id, 'RETIFICACAO', motivo).subscribe({
      next: () => {
        this.message.warning('Retificação solicitada ao servidor.');
        this.fecharModal();
        this.carregarRelatorios();
      },
      error: () => this.message.error('Erro ao solicitar retificação.')
    });
  }

  imprimirModal(): void {
    document.querySelectorAll('#vlibras-access-wrapper, #vlibras-app-root, [id*="vlibras"], [vw]').forEach(el => {
      (el as HTMLElement).style.setProperty('display', 'none', 'important');
    });
    window.print();
  }

  formatarData(dt: string | undefined): string {
    if (!dt) return '-';
    return new Date(dt.replace(' ', 'T')).toLocaleDateString('pt-BR');
  }

  getNomeMes(mes: number): string {
    const m = this.meses.find(x => x.valor === mes);
    return m?.nome || String(mes);
  }
}
