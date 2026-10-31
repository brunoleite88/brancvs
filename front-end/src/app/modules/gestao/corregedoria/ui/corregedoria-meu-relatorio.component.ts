import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CorregedoriaApiClient, RelatorioCorregedoria, RelatorioAto, RelatorioCabecalho } from '../infra/corregedoria-api.client';
import { AuthService } from 'src/app/services/auth.service';
import { MessageService } from 'src/app/v2/services/message.service';
import { BreadcrumbComponent } from 'src/app/v2/components/breadcrumb/breadcrumb.component';
import { BreadcrumbService } from 'src/app/v2/components/breadcrumb/breadcrumb.service';

@Component({
  selector: 'app-corregedoria-meu-relatorio',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [CommonModule, FormsModule, BreadcrumbComponent],
  providers: [BreadcrumbService, CorregedoriaApiClient],
  templateUrl: './corregedoria-meu-relatorio.component.html',
  styleUrls: ['./corregedoria-meu-relatorio.component.scss'],
})
export class CorregedoriaMeuRelatorioComponent implements OnInit {
  private readonly api = inject(CorregedoriaApiClient);
  readonly auth = inject(AuthService);
  private readonly message = inject(MessageService);
  private readonly cd = inject(ChangeDetectorRef);

  readonly relatorio = signal<RelatorioCorregedoria | null>(null);
  readonly carregando = signal(true);
  readonly salvando = signal(false);

  readonly modalCabecalhoAberto = signal(false);
  readonly cabecalhoEdit = signal<RelatorioCabecalho>({});
  readonly salvarComoPadrao = signal<boolean>(true);

  readonly mesSelecionado = signal<number>(10);
  readonly anoSelecionado = signal<number>(2026);

  readonly meses = [
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

  readonly atosSugeridos = [
    'Objetivos e Planos de Metas',
    'Reunião',
    'Parecer Jurídico',
    'Minuta de Peça / Contestação',
    'Despacho / Encaminhamento',
    'Análise de Processo Administrativo',
    'Triagem de Processos',
    'Audiência / Sessão de Julgamento',
    'Elaboração de Minuta de Lei / Decreto',
    'Disparador de Mensagens',
    'Encaminhamento de Mensagens',
    'Outros',
  ];

  ngOnInit(): void {
    const hoje = new Date();
    // Inicia no mês/ano atual ou outubro 2026
    this.mesSelecionado.set(hoje.getMonth() + 1);
    this.anoSelecionado.set(hoje.getFullYear() < 2026 ? 2026 : hoje.getFullYear());
    this.carregarRelatorio();
  }

  carregarRelatorio(): void {
    this.carregando.set(true);
    this.api.getMeuRelatorio(this.mesSelecionado(), this.anoSelecionado()).subscribe({
      next: (res) => {
        if (!res.cabecalho) {
          res.cabecalho = this.obterCabecalhoPadrao(res);
        }
        this.relatorio.set(res);
        // Se atos estiver vazio, adiciona a primeira linha automaticamente
        if (!res.atos || res.atos.length === 0) {
          this.adicionarAto();
        }
        this.carregando.set(false);
        this.cd.markForCheck();
      },
      error: (e) => {
        this.carregando.set(false);
        this.cd.markForCheck();
        this.message.error(e?.error?.error || 'Erro ao carregar relatório da corregedoria.');
      }
    });
  }

  abrirModalCabecalho(): void {
    const rel = this.relatorio();
    const atual = rel?.cabecalho || this.obterCabecalhoPadrao(rel);
    this.cabecalhoEdit.set({
      logo_url: atual.logo_url || 'assets/images/brasao_ma.png',
      ente: atual.ente || 'ESTADO DO MARANHÃO',
      orgao: atual.orgao || 'PROCURADORIA GERAL DO ESTADO',
      titulo: atual.titulo || 'RELATÓRIO DE ATIVIDADES',
      subtitulo: atual.subtitulo || rel?.unidade?.nome || 'PROCURADORIA GERAL DO ESTADO',
      altura_logo: atual.altura_logo || 68,
    });
    this.modalCabecalhoAberto.set(true);
  }

  fecharModalCabecalho(): void {
    this.modalCabecalhoAberto.set(false);
  }

  onUploadLogo(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files[0]) {
      const file = input.files[0];
      if (file.size > 2 * 1024 * 1024) {
        this.message.error('A imagem da logo deve ter no máximo 2MB.');
        return;
      }
      const reader = new FileReader();
      reader.onload = (e) => {
        const dataUrl = e.target?.result as string;
        this.cabecalhoEdit.update(c => ({ ...c, logo_url: dataUrl }));
        this.cd.markForCheck();
      };
      reader.readAsDataURL(file);
    }
  }

  usarLogoPadrao(tipo: 'brasao' | 'pge' | 'nenhum'): void {
    if (tipo === 'brasao') {
      this.cabecalhoEdit.update(c => ({ ...c, logo_url: 'assets/images/brasao_ma.png' }));
    } else if (tipo === 'pge') {
      this.cabecalhoEdit.update(c => ({ ...c, logo_url: 'assets/images/icon_blue.png' }));
    } else {
      this.cabecalhoEdit.update(c => ({ ...c, logo_url: '' }));
    }
  }

  restaurarCabecalhoPadrao(): void {
    const rel = this.relatorio();
    this.cabecalhoEdit.set({
      logo_url: 'assets/images/brasao_ma.png',
      ente: 'ESTADO DO MARANHÃO',
      orgao: 'PROCURADORIA GERAL DO ESTADO',
      titulo: 'RELATÓRIO DE ATIVIDADES',
      subtitulo: rel?.unidade?.nome || 'PROCURADORIA GERAL DO ESTADO',
      altura_logo: 68,
    });
  }

  salvarCabecalho(): void {
    const editado = this.cabecalhoEdit();
    const rel = this.relatorio();
    if (!rel) return;

    const atualizado = { ...rel, cabecalho: editado };
    this.relatorio.set(atualizado);

    if (this.salvarComoPadrao()) {
      try {
        localStorage.setItem('pge_corregedoria_cabecalho_padrao', JSON.stringify(editado));
      } catch {}
    }

    this.fecharModalCabecalho();
    this.salvarRascunho();
  }

  obterCabecalhoPadrao(rel?: RelatorioCorregedoria | null): RelatorioCabecalho {
    try {
      const salvo = localStorage.getItem('pge_corregedoria_cabecalho_padrao');
      if (salvo) return JSON.parse(salvo);
    } catch {}
    return {
      logo_url: 'assets/images/brasao_ma.png',
      ente: 'ESTADO DO MARANHÃO',
      orgao: 'PROCURADORIA GERAL DO ESTADO',
      titulo: 'RELATÓRIO DE ATIVIDADES',
      subtitulo: rel?.unidade?.nome || 'PROCURADORIA GERAL DO ESTADO',
      altura_logo: 68,
    };
  }

  mudarPeriodo(mes: number, ano: number): void {
    this.mesSelecionado.set(mes);
    this.anoSelecionado.set(ano);
    this.carregarRelatorio();
  }

  get mesExtenso(): string {
    const m = this.meses.find(x => x.valor === this.mesSelecionado());
    return (m?.nome || '').toUpperCase();
  }

  get isEditavel(): boolean {
    const st = this.relatorio()?.status;
    return st === 'RASCUNHO' || st === 'RETIFICACAO';
  }

  adicionarAto(): void {
    const rel = this.relatorio();
    if (!rel) return;
    const novosAtos = [...(rel.atos || [])];
    const dtFormatada = `${this.anoSelecionado()}-${String(this.mesSelecionado()).padStart(2, '0')}-01`;
    novosAtos.push({
      numero_ordem: novosAtos.length + 1,
      processo: '',
      ato: '',
      data_ato: dtFormatada,
      observacao: '',
    });
    this.relatorio.set({ ...rel, atos: novosAtos });
  }

  removerAto(index: number): void {
    const rel = this.relatorio();
    if (!rel || !this.isEditavel) return;
    const novosAtos = rel.atos.filter((_, i) => i !== index).map((ato, i) => ({
      ...ato,
      numero_ordem: i + 1,
    }));
    this.relatorio.set({ ...rel, atos: novosAtos });
  }

  salvarRascunho(): void {
    const rel = this.relatorio();
    if (!rel) return;
    this.salvando.set(true);
    this.api.salvarRascunho(rel).subscribe({
      next: (atualizado) => {
        this.relatorio.set(atualizado);
        this.salvando.set(false);
        this.message.success('Rascunho salvo com sucesso!');
      },
      error: (e) => {
        this.salvando.set(false);
        this.message.error(e?.error?.error || 'Erro ao salvar rascunho.');
      }
    });
  }

  assinar(): void {
    const rel = this.relatorio();
    if (!rel) return;

    if (!rel.atos || rel.atos.length === 0 || !rel.atos.some(a => a.ato)) {
      this.message.error('Preencha ao menos uma linha de atividade antes de assinar.');
      return;
    }

    if (!confirm('Deseja consolidar e assinar digitalmente este relatório? Após a assinatura, os dados serão congelados e encaminhados para a Corregedoria.')) {
      return;
    }

    this.salvando.set(true);
    // Salva primeiro para garantir consistência
    this.api.salvarRascunho(rel).subscribe({
      next: (salvo) => {
        this.api.assinarRelatorio(salvo.id).subscribe({
          next: (assinado) => {
            this.relatorio.set(assinado);
            this.salvando.set(false);
            this.message.success('Relatório consolidado e assinado digitalmente com sucesso!');
          },
          error: (e) => {
            this.salvando.set(false);
            this.message.error(e?.error?.error || 'Erro ao assinar relatório.');
          }
        });
      },
      error: (e) => {
        this.salvando.set(false);
        this.message.error(e?.error?.error || 'Erro ao salvar rascunho.');
      }
    });
  }

  imprimir(): void {
    window.print();
  }

  formatarData(dt: string | undefined): string {
    if (!dt) return '-';
    return new Date(dt.replace(' ', 'T')).toLocaleDateString('pt-BR');
  }
}
